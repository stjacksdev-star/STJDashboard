<?php

namespace App\Services;

use App\Services\StjApi\DashboardApiClient;
use Illuminate\Http\Client\RequestException;

class StoreProfileService
{
    public function __construct(
        private readonly DashboardApiClient $api,
    ) {
    }

    /**
     * @param array<string, mixed>|null $user
     * @return array<string, mixed>|null
     */
    public function enrich(?array $user): ?array
    {
        if (! is_array($user)) {
            return $user;
        }

        $store = trim((string) ($user['tiendas'] ?? ''));

        if ($store === '' || $store === '00000') {
            $user['storeLabel'] = $store !== '' ? $store : '00000';

            return $user;
        }

        if (filled($user['storeLabel'] ?? null)) {
            return $user;
        }

        $country = (string) ($user['idPais'] ?? $user['pais'] ?? '');

        if ($country === '' || $country === '0') {
            $user['storeLabel'] = $store;

            return $user;
        }

        try {
            $pending = $this->api->salesOrders([
                'country' => $country,
                'pending' => true,
                'store' => $store,
            ]);
            $name = trim((string) ($pending['filters']['storeName'] ?? ''));
            $code = trim((string) ($pending['filters']['store'] ?? ''));
            $user['storeLabel'] = $name !== ''
                ? $name.($code !== '' ? " ({$code})" : '')
                : $store;
        } catch (RequestException) {
            $user['storeLabel'] = $store;
        }

        return $user;
    }
}
