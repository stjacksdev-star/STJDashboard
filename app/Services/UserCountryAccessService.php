<?php

namespace App\Services;

use App\Services\StjApi\DashboardApiClient;
use Throwable;

class UserCountryAccessService
{
    public function __construct(
        private readonly DashboardApiClient $api,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allowedCountries(?array $user): array
    {
        try {
            $payload = $this->api->currentUserCountryAccess($this->actor($user));
            $countries = $payload['allowedCountries'] ?? [];

            if (is_array($countries) && $countries !== []) {
                return $countries;
            }
        } catch (Throwable) {
            // Fall back to the CAS session country when stj-api is unavailable.
        }

        $base = $this->baseCountry($user);

        return $base ? [$base] : [];
    }

    public function defaultCountry(?array $user): ?array
    {
        return collect($this->allowedCountries($user))
            ->first(fn (array $country) => (bool) ($country['isDefault'] ?? false))
            ?? $this->baseCountry($user);
    }

    /**
     * @return array<int, int>
     */
    public function allowedCountryIds(?array $user): array
    {
        return collect($this->allowedCountries($user))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function allowedCountryCodes(?array $user): array
    {
        return collect($this->allowedCountries($user))
            ->pluck('code')
            ->map(fn ($code) => strtoupper((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function canAccessCountry(?array $user, mixed $country): bool
    {
        $value = trim((string) $country);

        if ($value === '' || strtoupper($value) === 'TODO') {
            return true;
        }

        if (is_numeric($value)) {
            return in_array((int) $value, $this->allowedCountryIds($user), true);
        }

        return in_array(strtoupper($value), $this->allowedCountryCodes($user), true);
    }

    /**
     * @param array<int, array<string, mixed>> $countries
     * @return array<int, array<string, mixed>>
     */
    public function filterCountries(?array $user, array $countries): array
    {
        $ids = $this->allowedCountryIds($user);
        $codes = $this->allowedCountryCodes($user);

        return collect($countries)
            ->filter(function (array $country) use ($ids, $codes) {
                $id = (int) ($country['id'] ?? 0);
                $code = strtoupper((string) ($country['code'] ?? ''));

                return in_array($id, $ids, true) || in_array($code, $codes, true);
            })
            ->values()
            ->all();
    }

    public function firstAllowedCountry(?array $user): ?array
    {
        return collect($this->allowedCountries($user))->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function actor(?array $user): array
    {
        return [
            'id' => $user['idUser'] ?? $user['id'] ?? null,
            'name' => $user['nombre'] ?? $user['name'] ?? null,
            'email' => $user['correo'] ?? $user['email'] ?? null,
            'username' => $user['usuario'] ?? $user['username'] ?? null,
            'countryId' => $user['idPais'] ?? null,
            'countryCode' => $user['pais'] ?? null,
        ];
    }

    private function baseCountry(?array $user): ?array
    {
        $id = $user['idPais'] ?? null;
        $code = strtoupper((string) ($user['pais'] ?? ''));

        if (! is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return [
            'id' => (int) $id,
            'code' => $code !== '' ? $code : (string) $id,
            'name' => $code !== '' ? $code : 'Pais '.$id,
            'isDefault' => true,
            'source' => 'session',
        ];
    }
}
