<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Services\UserCountryAccessService;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function catalog(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_CITAS')) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:3'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);

        if (! $this->canUseGlobalFilters($permissions)) {
            $validated['country'] = (string) ($user['idPais'] ?? '');
        }

        if (filled($validated['country'] ?? null) && ! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            $data = $api->appointmentCatalog($validated['country'] ?? null);
            $data['countries'] = $countryAccess->filterCountries($user, $data['countries'] ?? []);

            return response()->json([
                'ok' => true,
                'data' => $data,
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible cargar el catalogo de citas desde stj-api.');
        }
    }

    public function index(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_CITAS')) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'store' => ['nullable', 'string', 'max:20'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);

        if (! $this->canUseGlobalFilters($permissions)) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeId'] ?? $user['storeCode'] ?? $user['tiendas'] ?? '');
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->appointments($validated),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener las citas desde stj-api.');
        }
    }

    /**
     * @param array<int, string> $permissions
     */
    private function canUseGlobalFilters(array $permissions): bool
    {
        return in_array('ROOT', $permissions, true)
            || in_array('STIE', $permissions, true)
            || in_array('GERENTE', $permissions, true)
            || in_array('ATEC', $permissions, true)
            || in_array('ADMINEC', $permissions, true)
            || in_array('SUPERVISOR', $permissions, true)
            || in_array('REGIONAL', $permissions, true);
    }

    private function countryForbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para consultar citas de este pais.',
        ], 403);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para ver citas.',
        ], 403);
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
