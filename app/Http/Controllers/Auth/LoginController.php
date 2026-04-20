<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CasAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(Request $request, CasAuthService $cas): Response|RedirectResponse
    {
        if ($this->callbackToken($request) !== null) {
            return $this->authenticateToken($request, $cas);
        }

        if ($request->session()->has('stj.user')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'casLoginUrl' => $cas->loginUrl($cas->callbackUrl()),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function callback(Request $request, CasAuthService $cas): RedirectResponse
    {
        if ($this->callbackToken($request) !== null) {
            return $this->authenticateToken($request, $cas);
        }

        return $this->authenticateToken($request, $cas);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('stj');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function authenticateToken(Request $request, CasAuthService $cas): RedirectResponse
    {
        $token = $this->callbackToken($request);

        Log::info('CAS callback received', [
            'path' => $request->path(),
            'query_keys' => array_keys($request->query()),
            'has_token' => $token !== null,
        ]);

        if ($token === null) {
            return redirect()
                ->route('login')
                ->with('status', [
                    'type' => 'warning',
                    'title' => 'AVISO',
                    'message' => 'CAS retorno al dashboard, pero no envio token para validar la sesion.',
                ]);
        }

        $validation = $cas->validateToken($token);

        if ($validation['ok'] === true) {
            $request->session()->regenerate();
            $request->session()->put('stj.user', $validation['user']);
            $request->session()->put('stj.expires_at', $validation['expires_at']);

            Log::info('CAS session started', [
                'idUser' => $validation['user']['idUser'] ?? null,
                'correo' => $validation['user']['correo'] ?? null,
                'expires_at' => $validation['expires_at'] ?? null,
            ]);

            return redirect()->intended(route('dashboard'));
        }

        Log::warning('CAS validation failed', [
            'message' => $validation['message'],
            'path' => $request->path(),
        ]);

        return redirect()
            ->route('login')
            ->with('status', [
                'type' => 'warning',
                'title' => 'AVISO',
                'message' => $validation['message'],
            ]);
    }

    private function callbackToken(Request $request): ?string
    {
        foreach (['token', 'bearer', 'access_token'] as $key) {
            if ($request->filled($key)) {
                return (string) $request->query($key);
            }
        }

        return null;
    }
}
