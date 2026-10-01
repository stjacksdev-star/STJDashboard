<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ManagementReportController extends Controller
{
    public function dailySales(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->allowed($request)) {
            return $this->forbidden();
        }

        $filters = $request->validate($this->rules());

        try {
            return response()->json(['ok' => true, 'data' => $api->managementDailySales($filters)]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible cargar el reporte desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function dailySalesExport(Request $request, DashboardApiClient $api): Response|JsonResponse
    {
        if (! $this->allowed($request)) {
            return $this->forbidden();
        }

        $filters = $request->validate($this->rules());

        try {
            $response = $api->managementDailySalesExport($filters);

            return response($response->body(), $response->status(), [
                'Content-Type' => $response->header('Content-Type'),
                'Content-Disposition' => $response->header('Content-Disposition'),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (RequestException $exception) {
            return response()->json(['ok' => false, 'message' => 'No fue posible exportar el reporte desde stj-api.'], $exception->response?->status() ?: 502);
        }
    }

    private function allowed(Request $request): bool
    {
        return DashboardAccess::can($request->session()->get('stj.user'), 'MENU_REPO_VENTA_GERE');
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => 'No tiene permiso para consultar reportes de Gerencias.'], 403);
    }

    private function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'country' => ['nullable', 'integer', 'in:0,1,2,3,7'],
        ];
    }
}
