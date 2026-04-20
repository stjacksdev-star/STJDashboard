<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CasAuthService
{
    public function loginUrl(string $callbackUrl): string
    {
        $baseUrl = rtrim((string) config('stj.cas.base_url'), '/');

        return $baseUrl.'?'.http_build_query([
            'service' => config('stj.cas.signature'),
            'url' => $callbackUrl,
            'token' => Str::random(32),
            'redirect' => 'slf',
        ]);
    }

    public function callbackUrl(): string
    {
        return config('stj.cas.callback_url') ?: route('login');
    }

    /**
     * @return array{ok: bool, user?: array<string, mixed>, expires_at?: int, ttl?: int, message?: string}
     */
    public function validateToken(string $token): array
    {
        $signature = config('stj.cas.signature');

        if (blank($signature)) {
            return [
                'ok' => false,
                'message' => 'No se ha configurado la firma del servicio CAS.',
            ];
        }

        try {
            $response = Http::timeout((int) config('stj.cas.timeout'))
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Signature' => 'Token '.$signature,
                    'STJ' => 'Bearer '.$token,
                    'Origin' => config('stj.cas.origin'),
                    'Referer' => config('stj.cas.origin'),
                ])
                ->get((string) config('stj.cas.validate_url'));
        } catch (ConnectionException $exception) {
            Log::warning('CAS connection error', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Error al validar su sesion.',
            ];
        } catch (Throwable $exception) {
            Log::warning('CAS unexpected error', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'No fue posible conectar con el servicio de autenticacion.',
            ];
        }

        if (! $response->successful()) {
            Log::warning('CAS validation returned unsuccessful response', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return [
                'ok' => false,
                'message' => 'El servicio de autenticacion no respondio correctamente.',
            ];
        }

        $payload = $response->json() ?: [];

        if (! Arr::get($payload, 'resultado')) {
            Log::warning('CAS validation denied token', [
                'message' => Arr::get($payload, 'mensaje'),
            ]);

            return [
                'ok' => false,
                'message' => 'Su sesion no es valida, '.(Arr::get($payload, 'mensaje') ?: 'intente iniciar sesion nuevamente.'),
            ];
        }

        $expiresAt = (int) Arr::get($payload, 'cas.exp', now()->addMinutes(config('session.lifetime'))->timestamp);
        $ttl = max(1, $expiresAt - now()->timestamp);
        $data = (array) Arr::get($payload, 'cas.data', []);
        $country = strtoupper((string) ($data['pais'] ?? ''));

        return [
            'ok' => true,
            'expires_at' => $expiresAt,
            'ttl' => $ttl,
            'user' => [
                'bearer' => $token,
                'idUser' => $data['idUser'] ?? null,
                'nombre' => $data['nombre'] ?? null,
                'correo' => $data['correo'] ?? null,
                'usuario' => $data['usuario'] ?? null,
                'mydepartment' => $data['mydepartment'] ?? null,
                'operaciones' => $data['operaciones'] ?? [],
                'tiendas' => filled($data['tiendas'] ?? null) ? $data['tiendas'] : '00000',
                'pais' => $country,
                'idPais' => $this->countryId($country),
            ],
        ];
    }

    private function countryId(string $country): int
    {
        return match ($country) {
            'SV' => 1,
            'GT' => 2,
            'CR' => 3,
            'NI' => 4,
            'PA' => 5,
            default => 0,
        };
    }
}
