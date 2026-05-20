<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Services\UserCountryAccessService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function kpi(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:3'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $country = $validated['country'] ?? null;

        if (filled($country) && ! $countryAccess->canAccessCountry($user, $country)) {
            return $this->countryForbidden();
        }

        try {
            $data = $api->salesKpi(
                $country,
                $validated['startDate'] ?? null,
                $validated['endDate'] ?? null,
            );
            $data['countries'] = $countryAccess->filterCountries($user, $data['countries'] ?? []);

            return response()->json([
                'ok' => true,
                'data' => $data,
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener el KPI de ventas desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function catalog(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:3'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $country = $validated['country'] ?? null;

        if (filled($country) && ! $countryAccess->canAccessCountry($user, $country)) {
            return $this->countryForbidden();
        }

        try {
            $data = $api->salesCatalog($country);
            $data['countries'] = $countryAccess->filterCountries($user, $data['countries'] ?? []);

            return response()->json([
                'ok' => true,
                'data' => $data,
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener el catalogo de ventas desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function regionalChart(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        $validated = $request->validate([
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        try {
            $data = $api->regionalSalesChart(
                $validated['startDate'] ?? null,
                $validated['endDate'] ?? null,
            );

            return response()->json([
                'ok' => true,
                'data' => $this->filterRegionalChart($countryAccess->allowedCountryIds($user), $data),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener el grafico regional de ventas desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function conversion(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'country' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->conversionChart(
                    $validated['startDate'] ?? null,
                    $validated['endDate'] ?? null,
                    $validated['country'] ?? null,
                ),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener la conversion desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function visits(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'country' => ['nullable', 'string', 'max:20'],
            'previousStartDate' => ['nullable', 'date'],
            'previousEndDate' => ['nullable', 'date'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->visitsChart($validated),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener las visitas desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function satisfaction(DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->satisfaction(),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener los indicadores de satisfaccion desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function categories(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->categorySales(
                    $validated['startDate'] ?? null,
                    $validated['endDate'] ?? null,
                ),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener la venta por categorias desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function segments(DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->segments(),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener segmentos desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function paymentForms(DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->paymentForms(),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener formas de pago desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function geographic(DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->geographicSales(),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener venta geografica desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function app(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->appInstallations($validated['year'] ?? null),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener las instalaciones APP desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function orders(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if ($request->has('pending')) {
            $request->merge([
                'pending' => filter_var($request->input('pending'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'origin' => ['nullable', 'string', 'max:20'],
            'checkout' => ['nullable', 'string', 'max:20'],
            'pending' => ['nullable', 'boolean'],
            'statuses' => ['nullable', 'string', 'max:255'],
            'store' => ['nullable', 'string', 'max:20'],
        ]);

        if (! $countryAccess->canAccessCountry((array) $request->session()->get('stj.user', []), $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->salesOrders($validated),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener el detalle de pedidos desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    private function countryForbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para consultar ventas de este pais.',
        ], 403);
    }

    private function filterRegionalChart(array $allowedCountryIds, array $data): array
    {
        $allowedCountryIds = array_values(array_unique(array_map('intval', $allowedCountryIds)));
        $regionalCountryIds = [1, 2, 3, 7];
        $canSeeConsolidated = empty(array_diff($regionalCountryIds, $allowedCountryIds));

        $data['series'] = collect($data['series'] ?? [])
            ->filter(function (array $serie) use ($allowedCountryIds, $canSeeConsolidated) {
                $countryId = (int) ($serie['countryId'] ?? 0);

                if ($countryId === 0) {
                    return $canSeeConsolidated;
                }

                return in_array($countryId, $allowedCountryIds, true);
            })
            ->values()
            ->all();

        return $data;
    }
}
