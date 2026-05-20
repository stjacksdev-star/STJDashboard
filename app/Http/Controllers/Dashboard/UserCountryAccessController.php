<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserCountryAccessController extends Controller
{
    public function index(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->userCountryAccess($this->actor($request)),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener las asignaciones desde stj-api.');
        }
    }

    public function users(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->userCountryAccessUsers($validated['search'] ?? null),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener los usuarios desde stj-api.');
        }
    }

    public function store(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'casUserId' => ['required', 'integer', 'min:1'],
            'username' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:180'],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*.id' => ['required', 'integer', 'min:1'],
            'countries.*.code' => ['required', 'string', 'max:5'],
            'countries.*.name' => ['nullable', 'string', 'max:120'],
            'defaultCountryId' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->saveUserCountryAccess($validated, $this->actor($request)),
                'message' => 'Asignacion de paises guardada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible guardar la asignacion en stj-api.');
        }
    }

    public function destroy(Request $request, int $assignment, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        try {
            $api->deleteUserCountryAccess($assignment);

            return response()->json([
                'ok' => true,
                'message' => 'Pais removido del usuario correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible remover el pais en stj-api.');
        }
    }

    private function canManage(Request $request): bool
    {
        $user = $request->session()->get('stj.user');

        return DashboardAccess::can($user, 'MENU_CONFIGURACION');
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para administrar paises por usuario.',
        ], 403);
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
            'countryId' => $user['idPais'] ?? null,
            'countryCode' => $user['pais'] ?? null,
            'ip' => $request->ip(),
            'userAgent' => substr((string) $request->userAgent(), 0, 500),
        ];
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
