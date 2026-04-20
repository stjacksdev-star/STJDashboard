<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    public function index(Request $request, DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->collections($request->string('country')->toString()),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener las colecciones desde stj-api.',
            ], $exception->response?->status() ?: 502);
        }
    }

    public function store(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'name' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:100'],
            'banner' => ['required', 'image', 'max:5120'],
            'products' => ['required', 'file', 'max:5120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->createCollection(
                    [
                        ...$validated,
                        'mobilePosition' => 'right',
                    ],
                    $request->file('banner'),
                    $request->file('products'),
                ),
                'message' => 'Coleccion creada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible crear la coleccion en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function update(Request $request, int $collection, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'name' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:100'],
            'banner' => ['nullable', 'image', 'max:5120'],
            'products' => ['nullable', 'file', 'max:5120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updateCollection(
                    $collection,
                    [
                        ...$validated,
                        'mobilePosition' => 'right',
                    ],
                    $request->file('banner'),
                    $request->file('products'),
                ),
                'message' => 'Coleccion actualizada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible actualizar la coleccion en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function assets(int $collection, DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->collectionAssets($collection),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener los assets desde stj-api.',
            ], $exception->response?->status() ?: 502);
        }
    }

    public function storeAsset(Request $request, int $collection, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['CUPON', 'LO-MAS-NUEVO', 'BANNER', 'MODAL', 'SLIDER'])],
            'platform' => ['nullable', Rule::in(['TODO', 'WEB', 'APP'])],
            'position' => ['nullable', Rule::in(['DERECHA', 'IZQUIERDA', 'CENTRO'])],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['ACTIVO', 'PENDIENTE', 'CANCELADO', 'FINALIZADO'])],
            'startAt' => ['required', 'date'],
            'endAt' => ['required', 'date', 'after_or_equal:startAt'],
            'title' => ['nullable', 'string', 'max:45'],
            'image' => ['required', 'image', 'max:5120'],
            'mobileImage' => ['nullable', 'image', 'max:5120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->createCollectionAsset(
                    $collection,
                    $validated,
                    $request->file('image'),
                    $request->file('mobileImage'),
                ),
                'message' => 'Asset creado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible crear el asset en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }
}
