<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(Request $request, DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->promotions(
                    $request->string('country')->toString(),
                    $request->string('status')->toString(),
                ),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener las promociones desde stj-api.',
            ], $exception->response?->status() ?: 502);
        }
    }

    public function store(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'name' => ['required', 'string', 'max:100'],
            'commercialName' => ['nullable', 'string', 'max:255'],
            'origin' => ['required', Rule::in(['TODO', 'WEB', 'APP'])],
            'checkoutType' => ['nullable', Rule::in(['TODO', 'D', 'T'])],
            'type' => ['required', Rule::in(['TODO', 'CATEGORIA', 'SUB-CATEGORIA', 'SKU', 'TARJETA', 'ENTREGA'])],
            'promotionType' => ['required', Rule::in(['DESCUENTO', 'DESCUENTO-SKU', 'PUNTO-PRECIO', 'CONDICION-SKU', 'CONDICION-ENTREGA', 'CONDICION-PAGO'])],
            'restriction' => ['nullable', Rule::in(['TOTAL_COMPRA', '21/2', '2x1', '3x2', '2doPrecio', 'TARJETA', 'ENTREGA', '2xPP'])],
            'price' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'startAt' => ['required', 'date'],
            'endAt' => ['required', 'date', 'after:startAt'],
            'products' => ['nullable', 'file', 'max:5120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->createPromotion($validated, $request->file('products')),
                'message' => 'Promocion creada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible crear la promocion en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function updateSchedule(Request $request, int $promotion, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'commercialName' => ['nullable', 'string', 'max:255'],
            'startAt' => ['nullable', 'date'],
            'endAt' => ['nullable', 'date'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updatePromotionSchedule($promotion, $validated),
                'message' => 'Horario de promocion actualizado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible actualizar el horario en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function assets(int $promotion, DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->promotionAssets($promotion),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener los assets desde stj-api.',
            ], $exception->response?->status() ?: 502);
        }
    }

    public function storeAsset(Request $request, int $promotion, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate($this->assetRules(imageRequired: true));

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->createPromotionAsset(
                    $promotion,
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

    public function updateAsset(Request $request, int $asset, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate($this->assetRules(imageRequired: false));

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updatePromotionAsset(
                    $asset,
                    $validated,
                    $request->file('image'),
                    $request->file('mobileImage'),
                ),
                'message' => 'Asset actualizado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible actualizar el asset en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function destroyAsset(int $asset, DashboardApiClient $api): JsonResponse
    {
        try {
            $api->deletePromotionAsset($asset);

            return response()->json([
                'ok' => true,
                'message' => 'Asset eliminado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible eliminar el asset en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assetRules(bool $imageRequired): array
    {
        return [
            'type' => ['required', Rule::in(['CUPON', 'LO-MAS-NUEVO', 'BANNER', 'MODAL', 'SLIDER'])],
            'platform' => ['nullable', Rule::in(['TODO', 'WEB', 'APP'])],
            'position' => ['nullable', Rule::in(['DERECHA', 'IZQUIERDA', 'CENTRO'])],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['ACTIVO', 'PENDIENTE', 'CANCELADO', 'FINALIZADO'])],
            'startAt' => ['required', 'date'],
            'endAt' => ['required', 'date', 'after_or_equal:startAt'],
            'title' => ['nullable', 'string', 'max:45'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'max:5120'],
            'mobileImage' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function updateHeader(Request $request, int $promotion, DashboardApiClient $api): JsonResponse
    {
        $request->validate([
            'header' => ['required', 'image', 'max:5120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updatePromotionHeader($promotion, $request->file('header')),
                'message' => 'Banner de promocion actualizado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible actualizar el banner en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }
}
