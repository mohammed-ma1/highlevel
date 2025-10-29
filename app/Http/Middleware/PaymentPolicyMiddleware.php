<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PaymentPolicyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Add Feature-Policy header to allow payment requests from iframes
        $response->headers->set('Feature-Policy', 'payment *');
        
        // Add Permissions-Policy header (newer standard, better Safari support)
        $response->headers->set('Permissions-Policy', 'payment=*');
        
        // Add additional Safari-specific payment policy headers
        $response->headers->set('Feature-Policy', 'payment *; camera *; microphone *; geolocation *');
        $response->headers->set('Permissions-Policy', 'payment=*, camera=*, microphone=*, geolocation=*');
        
        // Add Content-Security-Policy to allow iframe embedding and Tap resources
        $response->headers->set('Content-Security-Policy', 
            "frame-ancestors 'self' https://app.gohighlevel.com https://*.gohighlevel.com https://app.mediasolution.io https://*.mediasolution.io; " .
            "img-src 'self' data: https: http: https://www.gotapnow.com https://*.gotapnow.com https://api.tap.company https://*.tap.company; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: http:; " .
            "style-src 'self' 'unsafe-inline' https: http:; " .
            "font-src 'self' data: https: http:; " .
            "connect-src 'self' https: http:; " .
            "object-src 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self' https: http:"
        );
        
        // Safari-specific headers for iframe compatibility
        $response->headers->set('X-Frame-Options', 'ALLOWALL');
        
        // Add Safari-specific headers to allow iframe interactions
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Add CORS headers for Safari iframe compatibility
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        
        return $response;
    }
}