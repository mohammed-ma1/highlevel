<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PaymentAccessGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentAccessGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_muneera_alnkehlan_by_name(): void
    {
        $user = User::create([
            'name' => 'Muneera Alnkehlan',
            'email' => 'billing@example.com',
            'password' => Hash::make('secret'),
            'lead_location_id' => 'loc_blocked',
        ]);

        $this->assertTrue((new PaymentAccessGuard())->isBlocked($user));
    }

    public function test_allows_unrelated_merchant(): void
    {
        $user = User::create([
            'name' => 'Media Solution Client',
            'email' => 'client@mediasolution.io',
            'password' => Hash::make('secret'),
            'lead_location_id' => 'loc_ok',
        ]);

        $this->assertFalse((new PaymentAccessGuard())->isBlocked($user));
    }

    public function test_blocks_entire_company_when_owner_matches(): void
    {
        User::create([
            'name' => 'Muneera Alnkehlan',
            'email' => 'owner@example.com',
            'password' => Hash::make('secret'),
            'lead_location_id' => 'loc_owner',
            'lead_company_id' => 'co_123',
        ]);

        $subAccount = User::create([
            'name' => 'Sub Account',
            'email' => 'sub@example.com',
            'password' => Hash::make('secret'),
            'lead_location_id' => 'loc_sub',
            'lead_company_id' => 'co_123',
        ]);

        $this->assertTrue((new PaymentAccessGuard())->isBlocked($subAccount));
    }
}
