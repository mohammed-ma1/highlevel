<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class PaymentAccessGuard
{
    public const MESSAGE_AR = 'عذراً، بوابة الدفع هذه متاحة حصرياً للعملاء المشتركين في منصة ميديا سوليوشن. يرجى التواصل مع فريق الدعم للاشتراك أو تفعيل خدمتكم.';

    public const MESSAGE_EN = 'This payment gateway is available exclusively to Media Solution platform subscribers.';

    public function messageAr(): string
    {
        return self::MESSAGE_AR;
    }

    public function isBlocked(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->matchesBlockedLocationId($user->lead_location_id)) {
            return true;
        }

        if ($this->matchesBlockedIdentity($user)) {
            return true;
        }

        $companyId = trim((string) ($user->lead_company_id ?? ''));
        if ($companyId === '') {
            return false;
        }

        return User::query()
            ->where('lead_company_id', $companyId)
            ->get()
            ->contains(fn (User $candidate) => $this->matchesBlockedIdentity($candidate));
    }

    public function blockedJsonResponse(int $status = 403): JsonResponse
    {
        return response()->json([
            'success' => false,
            'blocked' => true,
            'message' => self::MESSAGE_AR,
            'message_en' => self::MESSAGE_EN,
        ], $status);
    }

    private function matchesBlockedLocationId(?string $locationId): bool
    {
        $locationId = trim((string) $locationId);
        if ($locationId === '') {
            return false;
        }

        $blocked = config('services.payment_access.blocked_location_ids', []);

        return in_array($locationId, $blocked, true);
    }

    private function matchesBlockedIdentity(User $user): bool
    {
        $fingerprint = $this->normalize(implode(' ', array_filter([
            $user->name,
            $user->email,
        ])));

        if ($fingerprint === '') {
            return false;
        }

        $hasMuneera = (bool) preg_match(
            '/\b(muneera|munira|monira|munirah|maneira|منيرة|منيره|منير)\b/u',
            $fingerprint
        );

        $hasAlnkehlan = (bool) preg_match(
            '/\b(alnkehlan|al[\s-]?nkehlan|al[\s-]?nkehl|alnkehl|nkehlan|nkehl|النخلان)\b/u',
            $fingerprint
        ) || str_contains($fingerprint, 'nkehlan') || str_contains($fingerprint, 'nkehl');

        return $hasMuneera && $hasAlnkehlan;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\s\-_]+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
