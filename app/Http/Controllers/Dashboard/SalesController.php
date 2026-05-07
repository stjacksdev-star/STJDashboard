<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function kpi(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:3'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->salesKpi(
                    $validated['country'] ?? null,
                    $validated['startDate'] ?? null,
                    $validated['endDate'] ?? null,
                ),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener el KPI de ventas desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function regionalChart(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->regionalSalesChart(
                    $validated['startDate'] ?? null,
                    $validated['endDate'] ?? null,
                ),
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

    public function orders(Request $request, DashboardApiClient $api): JsonResponse
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
}
