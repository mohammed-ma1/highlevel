<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentPolicyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Build comprehensive Content-Security-Policy
        $cspDirectives = [
            // Allow scripts from trusted sources
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://tap-sdks.b-cdn.net https://cdn.tap.company https://*.tap.company https://fonts.googleapis.com https://cdnjs.cloudflare.com https://*.gohighlevel.com https://*.leadconnectorhq.com https://*.gaztech.io blob:",
            
            // Allow stylesheets from trusted sources
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://*.tap.company",
            
            // Allow connections to APIs
            "connect-src 'self' https://api.tap.company https://*.tap.company https://*.gohighlevel.com https://*.leadconnectorhq.com https://*.mediasolution.io https://*.gaztech.io wss://*.tap.company https://fonts.googleapis.com https://fonts.gstatic.com",
            
            // Allow images from various sources
            "img-src 'self' data: https: blob:",
            
            // Allow fonts from trusted sources
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
            
            // Allow frames from trusted sources
            "frame-src 'self' https://*.tap.company https://*.gohighlevel.com https://*.leadconnectorhq.com https://*.mediasolution.io",
            
            // Allow this page to be embedded in iframes from these sources
            "frame-ancestors 'self' https://app.gohighlevel.com https://*.gohighlevel.com https://*.leadconnectorhq.com https://*.mediasolution.io *",
            
            // Allow form actions
            "form-action 'self' https://*.tap.company https://*.gohighlevel.com",
            
            // Base URI restriction
            "base-uri 'self'",
            
            // Default fallback
            "default-src 'self'",
            
            // Allow workers for SDK functionality
            "worker-src 'self' blob:",
            
            // Allow media
            "media-src 'self' https:",
            
            // Allow object/embed (needed for some payment widgets)
            "object-src 'none'",
        ];
        
        $csp = implode('; ', $cspDirectives);
        
        // Set the main CSP header (enforcing)
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Remove any report-only CSP that might cause warnings
        $response->headers->remove('Content-Security-Policy-Report-Only');
        
        // Add X-Frame-Options for older browser compatibility (but CSP frame-ancestors takes precedence)
        $response->headers->set('X-Frame-Options', 'ALLOWALL');
        
        // Add Permissions-Policy header to allow payment requests in iframe
        // This allows iframes to use the payment feature (required for Safari)
        $response->headers->set('Permissions-Policy', 'payment=*');
        
        // Add additional headers for Safari compatibility
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        return $response;
    }
}
