<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Services\UserCountryAccessService;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ClaimController extends Controller
{
    private const TYPES = [
        'devolucion',
        'retracto',
        'reversion_pago',
        'garantia',
        'cambio_talla',
        'producto_incorrecto',
        'entrega',
        'otro',
    ];

    private const ORIGINS = [
        'web',
        'tienda',
        'domicilio',
        'whatsapp',
        'correo',
        'telefono',
        'otro',
    ];

    private const AREAS = [
        'atencion_cliente',
        'ecommerce',
        'tienda',
        'logistica',
        'finanzas',
        'mercadeo',
        'otro',
    ];

    private const STATUSES = [
        'recibido',
        'en_revision',
        'asignado',
        'en_proceso',
        'resuelto',
        'rechazado',
        'cerrado',
    ];

    public function index(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! $this->canUseClaims($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'country' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ]);
        $validated['country'] = $this->resolveCountry($request, $countryAccess, $validated['country'] ?? null);

        if ($validated['country'] === null) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->claims($validated),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener los reclamos desde stj-api.');
        }
    }

    public function export(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): Response|JsonResponse
    {
        if (! $this->canUseClaims($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'country' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ]);
        $validated['country'] = $this->resolveCountry($request, $countryAccess, $validated['country'] ?? null);

        if ($validated['country'] === null) {
            return $this->countryForbidden();
        }

        try {
            $response = $api->exportClaims($validated);
            $disposition = $response->header('Content-Disposition') ?: 'attachment; filename="reclamos.xlsx"';

            return response($response->body(), 200, [
                'Content-Type' => $response->header('Content-Type') ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => $disposition,
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible exportar los reclamos desde stj-api.');
        }
    }

    public function store(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! $this->canUseClaims($request)) {
            return $this->forbidden();
        }

        $validated = $this->validateClaim($request);
        $photos = $request->file('photos', []);
        unset($validated['photos']);

        if (! $countryAccess->canAccessCountry((array) $request->session()->get('stj.user', []), $validated['country'])) {
            return $this->countryForbidden();
        }
        $validated['registeredBy'] = $this->sessionUserLabel($request);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->createClaim($validated, $this->actor($request), $photos),
                'message' => 'Reclamo creado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible crear el reclamo en stj-api.');
        }
    }

    public function update(Request $request, int $claim, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! $this->canUseClaims($request)) {
            return $this->forbidden();
        }

        $validated = $this->validateClaim($request);
        $photos = $request->file('photos', []);
        unset($validated['photos']);

        if (! $countryAccess->canAccessCountry((array) $request->session()->get('stj.user', []), $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updateClaim($claim, $validated, $this->actor($request), $photos),
                'message' => 'Reclamo actualizado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible actualizar el reclamo en stj-api.');
        }
    }

    public function destroy(Request $request, int $claim, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canUseClaims($request)) {
            return $this->forbidden();
        }

        try {
            $api->deleteClaim($claim);

            return response()->json([
                'ok' => true,
                'message' => 'Reclamo eliminado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible eliminar el reclamo en stj-api.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClaim(Request $request): array
    {
        return $request->validate([
            'managementNumber' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'integer', 'min:1'],
            'registeredAt' => ['nullable', 'date'],
            'stj' => ['nullable', 'string', 'max:50'],
            'customerName' => ['required', 'string', 'max:150'],
            'customerEmail' => ['nullable', 'email', 'max:150'],
            'customerPhone' => ['nullable', 'string', 'max:30'],
            'customerDui' => ['nullable', 'string', 'max:20'],
            'type' => ['required', Rule::in(self::TYPES)],
            'typeOther' => ['nullable', 'required_if:type,otro,otros', 'string', 'max:255'],
            'origin' => ['required', Rule::in(self::ORIGINS)],
            'originOther' => ['nullable', 'required_if:origin,otro,otros', 'string', 'max:255'],
            'responsibleArea' => ['required', Rule::in(self::AREAS)],
            'store' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'response' => ['nullable', 'string'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'rejectionReason' => ['nullable', 'string'],
            'resolvedAt' => ['nullable', 'date'],
            'closedAt' => ['nullable', 'date'],
            'registeredBy' => ['nullable', 'string', 'max:255'],
            'assignedTo' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:8192'],
        ]);
    }

    private function canUseClaims(Request $request): bool
    {
        return DashboardAccess::can($request->session()->get('stj.user'), 'MENU_RECLAMOS');
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para administrar reclamos.',
        ], 403);
    }

    private function countryForbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para administrar reclamos de este pais.',
        ], 403);
    }

    private function resolveCountry(Request $request, UserCountryAccessService $countryAccess, mixed $country): ?int
    {
        $user = (array) $request->session()->get('stj.user', []);

        if (filled($country)) {
            return $countryAccess->canAccessCountry($user, $country) ? (int) $country : null;
        }

        $default = $countryAccess->defaultCountry($user);

        return $default ? (int) $default['id'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(Request $request): array
    {
        $user = (array) $request->session()->get('stj.user', []);

        return [
            'id' => $user['idUser'] ?? $user['id'] ?? null,
            'name' => $user['nombre'] ?? $user['name'] ?? null,
            'email' => $user['correo'] ?? $user['email'] ?? null,
            'username' => $user['usuario'] ?? $user['username'] ?? null,
            'ip' => $request->ip(),
            'userAgent' => substr((string) $request->userAgent(), 0, 500),
        ];
    }

    private function sessionUserLabel(Request $request): ?string
    {
        $user = (array) $request->session()->get('stj.user', []);
        $name = trim((string) ($user['nombre'] ?? $user['name'] ?? ''));
        $email = trim((string) ($user['correo'] ?? $user['email'] ?? ''));
        $username = trim((string) ($user['usuario'] ?? $user['username'] ?? ''));
        $label = trim($name.($email !== '' ? " <{$email}>" : ''));

        return $label !== '' ? $label : ($username !== '' ? $username : null);
    }

    private function apiError(RequestException $exception, string $fallback): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $exception->response?->json('message') ?: $fallback,
            'errors' => $exception->response?->json('errors') ?: [],
        ], $exception->response?->status() ?: 502);
    }
}
