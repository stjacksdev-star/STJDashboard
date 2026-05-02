<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductMasterController extends Controller
{
    public function index(Request $request, DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->productMaster($request->string('search')->toString()),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener los productos desde stj-api.');
        }
    }

    public function import(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->importProductMaster($validated['file']),
                'message' => 'Importacion de productos finalizada.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible importar el maestro de productos en stj-api.');
        }
    }

    public function importPhotos(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->importProductPhotos($validated['file']),
                'message' => 'Importacion de fotografias finalizada.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible importar las fotografias en stj-api.');
        }
    }

    public function show(int $product, DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->productMasterDetail($product),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener el detalle del producto desde stj-api.');
        }
    }

    public function photos(int $product, DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->productMasterPhotos($product),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener las fotos del producto desde stj-api.');
        }
    }

    public function countries(int $product, DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->productMasterCountries($product),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener los paises del producto desde stj-api.');
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
