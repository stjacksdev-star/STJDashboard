<?php

namespace App\Http\Middleware;

use App\Services\StoreProfileService;
use App\Services\UserCountryAccessService;
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

        if (is_array($user)) {
            $user = $this->normalizeSessionCountry($user);

            if (blank($user['storeLabel'] ?? null) || blank($user['storeCode'] ?? null)) {
                $user = app(StoreProfileService::class)->enrich($user);
            }

            $request->session()->put('stj.user', $user);
        }

        $countryAccess = app(UserCountryAccessService::class);
        $allowedCountries = $countryAccess->allowedCountries((array) $user);

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user,
                'expiresAt' => $request->session()->get('stj.expires_at'),
                'permissions' => DashboardAccess::permissions($user),
                'countries' => [
                    'allowed' => $allowedCountries,
                    'default' => collect($allowedCountries)->first(fn (array $country) => (bool) ($country['isDefault'] ?? false)),
                ],
            ],
            'navigation' => DashboardMenu::forUser($user),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function normalizeSessionCountry(array $user): array
    {
        $id = (int) ($user['idPais'] ?? 0);

        if ($id > 0) {
            return $user;
        }

        $country = strtoupper((string) ($user['pais'] ?? ''));
        $resolved = match ($country) {
            'SV' => 1,
            'GT' => 2,
            'CR' => 3,
            'NI' => 4,
            'PA' => 5,
            'HN' => 7,
            default => 0,
        };

        if ($resolved > 0) {
            $user['idPais'] = $resolved;
        }

        return $user;
    }
}
