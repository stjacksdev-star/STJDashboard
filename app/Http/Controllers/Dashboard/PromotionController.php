<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Services\UserCountryAccessService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        $user = (array) $request->session()->get('stj.user', []);
        $country = $request->string('country')->toString();

        if (filled($country) && ! $countryAccess->canAccessCountry($user, $country)) {
            return $this->countryForbidden();
        }

        try {
            $data = $api->promotions(
                $country,
                $request->string('status')->toString(),
            );
            $data = $this->restrictPromotionPayload($data, $user, $countryAccess);

            return response()->json([
                'ok' => true,
                'data' => $data,
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener las promociones desde stj-api.',
            ], $exception->response?->status() ?: 502);
        }
    }

    public function store(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'name' => ['required', 'string', 'max:100'],
            'commercialName' => ['nullable', 'string', 'max:255'],
            'origin' => ['required', Rule::in(['TODO', 'WEB', 'APP'])],
            'checkoutType' => ['nullable', Rule::in(['TODO', 'D', 'T'])],
            'type' => ['required', Rule::in(['TODO', 'SKU'])],
            'promotionType' => ['required', Rule::in(['DESCUENTO', 'CONDICION-SKU', 'PUNTO-PRECIO', 'DESCUENTO-SKU'])],
            'restriction' => ['nullable', Rule::in(['21/2', '2x1', '2doPrecio', '2xPP'])],
            'price' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'startAt' => ['required', 'date'],
            'endAt' => ['required', 'date', 'after:startAt'],
            'products' => ['nullable', 'file', 'max:5120'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function restrictPromotionPayload(array $data, array $user, UserCountryAccessService $countryAccess): array
    {
        $allowedIds = $countryAccess->allowedCountryIds($user);
        $allowedCodes = $countryAccess->allowedCountryCodes($user);
        $data['countries'] = $countryAccess->filterCountries($user, $data['countries'] ?? []);
        $data['promotions'] = collect($data['promotions'] ?? [])
            ->filter(function (array $promotion) use ($allowedIds, $allowedCodes) {
                $country = $promotion['country'] ?? [];
                $id = (int) ($country['id'] ?? 0);
                $code = strtoupper((string) ($country['code'] ?? ''));

                return in_array($id, $allowedIds, true) || in_array($code, $allowedCodes, true);
            })
            ->values()
            ->all();

        return $data;
    }

    private function countryForbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para operar promociones de este pais.',
        ], 403);
    }

    public function updateSchedule(Request $request, int $promotion, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! $this->canAccessPromotion($api, $countryAccess, (array) $request->session()->get('stj.user', []), $promotion)) {
            return $this->countryForbidden();
        }

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

    public function assets(Request $request, int $promotion, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! $this->canAccessPromotion($api, $countryAccess, (array) $request->session()->get('stj.user', []), $promotion)) {
            return $this->countryForbidden();
        }

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

    public function storeAsset(Request $request, int $promotion, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! $this->canAccessPromotion($api, $countryAccess, (array) $request->session()->get('stj.user', []), $promotion)) {
            return $this->countryForbidden();
        }

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
            'title' => ['nullable', 'string', 'max:255'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'max:5120'],
            'mobileImage' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function updateHeader(Request $request, int $promotion, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! $this->canAccessPromotion($api, $countryAccess, (array) $request->session()->get('stj.user', []), $promotion)) {
            return $this->countryForbidden();
        }

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

    private function canAccessPromotion(DashboardApiClient $api, UserCountryAccessService $countryAccess, array $user, int $promotion): bool
    {
        try {
            $data = $api->promotions(null, null);
        } catch (RequestException) {
            return false;
        }

        return collect($data['promotions'] ?? [])
            ->contains(function (array $item) use ($promotion, $countryAccess, $user) {
                if ((int) ($item['id'] ?? 0) !== $promotion) {
                    return false;
                }

                $country = $item['country'] ?? [];

                return $countryAccess->canAccessCountry($user, $country['id'] ?? $country['code'] ?? null);
            });
    }
}
