<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCountryController extends Controller
{
    public function countries(DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->productCountryCountries(),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener los paises desde stj-api.');
        }
    }

    public function import(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->importProductCountry((int) $validated['country'], $validated['file']),
                'message' => 'Importacion de productos por pais finalizada.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible importar productos por pais en stj-api.');
        }
    }

    public function deactivate(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->deactivateProductCountry((int) $validated['country'], $validated['reason'], $validated['file']),
                'message' => 'Baja de productos por pais finalizada.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible inactivar productos por pais en stj-api.');
        }
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
