<?php

namespace App\Http\Middleware;

use App\Services\StoreProfileService;
use App\Support\DashboardAccess;
use App\Support\DashboardMenu;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->session()->get('stj.user');

        if (is_array($user) && blank($user['storeLabel'] ?? null)) {
            $user = app(StoreProfileService::class)->enrich($user);
            $request->session()->put('stj.user', $user);
        }

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user,
                'expiresAt' => $request->session()->get('stj.expires_at'),
                'permissions' => DashboardAccess::permissions($user),
            ],
            'navigation' => DashboardMenu::forUser($user),
        ];
    }
}
