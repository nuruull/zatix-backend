<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckDemoAccessExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        $demoRequest = $user->demoRequest;

        if (!$demoRequest || now()->greaterThan($demoRequest->demo_access_expiry)) {
            return response()->json([
                'message' => 'Akses demo Anda telah kedaluwarsa.'
            ], 403);
        }

        return $next($request);
    }
}
