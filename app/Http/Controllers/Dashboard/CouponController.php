<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Services\UserCountryAccessService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request, DashboardApiClient $api, UserCountryAccessService $access): JsonResponse
    {
        $user = (array) $request->session()->get('stj.user', []);
        $country = $request->string('country')->toString();
        if ($country && ! $access->canAccessCountry($user, $country)) return $this->forbidden();
        try {
            $data = $api->coupons(
                $country ?: null,
                $request->string('status')->toString() ?: null,
                $request->string('search')->toString() ?: null,
                $request->integer('page', 1),
                $request->integer('perPage', 20),
            );
            $data['countries'] = $access->filterCountries($user, $data['countries'] ?? []);
            $allowed = $access->allowedCountryCodes($user);
            $data['coupons'] = collect($data['coupons'] ?? [])->filter(fn ($item) => in_array(strtoupper($item['country']['code'] ?? ''), $allowed, true))->values()->all();
            return response()->json(['ok' => true, 'data' => $data]);
        } catch (RequestException $e) { return $this->apiError($e); }
    }

    public function store(Request $request, DashboardApiClient $api, UserCountryAccessService $access): JsonResponse
    {
        return $this->persist($request, $api, $access);
    }

    public function update(Request $request, int $coupon, DashboardApiClient $api, UserCountryAccessService $access): JsonResponse
    {
        return $this->persist($request, $api, $access, $coupon);
    }
    public function catalogs(Request $request, DashboardApiClient $api, UserCountryAccessService $access): JsonResponse
    {
        $country = $request->string('country')->toString(); $user = (array) $request->session()->get('stj.user', []);
        if (! $access->canAccessCountry($user, $country)) return $this->forbidden();
        try { return response()->json(['ok' => true, 'data' => $api->couponCatalogs($country)]); } catch (RequestException $e) { return $this->apiError($e); }
    }

    private function persist(Request $request, DashboardApiClient $api, UserCountryAccessService $access, ?int $coupon = null): JsonResponse
    {
        $data = $request->all();
        $user = (array) $request->session()->get('stj.user', []);
        if (! $access->canAccessCountry($user, $data['country'] ?? null)) return $this->forbidden();
        try {
            return response()->json(['ok' => true, 'data' => $api->saveCouponMultipart($data, $coupon, $request->file('productsFile'), $request->file('customersFile')), 'message' => $coupon ? 'Cupón actualizado.' : 'Cupón creado.']);
        } catch (RequestException $e) { return $this->apiError($e); }
    }

    private function forbidden(): JsonResponse { return response()->json(['ok' => false, 'message' => 'No tiene permiso para operar cupones de este país.'], 403); }
    private function apiError(RequestException $e): JsonResponse { return response()->json(['ok' => false, 'message' => $e->response?->json('message') ?: 'No fue posible procesar el cupón.', 'errors' => $e->response?->json('errors') ?: []], $e->response?->status() ?: 502); }
}
