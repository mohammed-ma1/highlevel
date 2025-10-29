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
        
        // Add Content-Security-Policy to allow iframe embedding from GoHighLevel
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' https://app.gohighlevel.com https://*.gohighlevel.com https://app.mediasolution.io https://*.mediasolution.io");
        
        // Add additional headers for iframe compatibility
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        
        return $response;
    }
}