<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCasAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $expiresAt = (int) $request->session()->get('stj.expires_at', 0);

        if (! $request->session()->has('stj.user') || $expiresAt <= now()->timestamp) {
            $request->session()->forget('stj');

            return redirect()->route('login');
        }

        return $next($request);
    }
}
