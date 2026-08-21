<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Services\UserCountryAccessService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPerformanceReportController extends Controller
{
    public function __invoke(Request $request, DashboardApiClient $api, UserCountryAccessService $access): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'max:3'], 'period' => ['required', 'in:7D,14D,30D,ANUAL'],
            'tab' => ['required', 'in:summary,sales,views,favorites,cart'], 'brand' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'integer'], 'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'], 'perPage' => ['nullable', 'integer', 'in:10,20,50,100'],
        ]);
        $user = (array) $request->session()->get('stj.user', []);
        if (! $access->canAccessCountry($user, $data['country'])) {
            return response()->json(['ok' => false, 'message' => 'No tiene acceso a este pais.'], 403);
        }
        try {
            return response()->json(['ok' => true, 'data' => $api->productPerformanceReport($data)]);
        } catch (RequestException $e) {
            return response()->json(['ok' => false, 'message' => $e->response?->json('message') ?: 'No fue posible cargar el reporte.', 'errors' => $e->response?->json('errors') ?: []], $e->response?->status() ?: 502);
        }
    }
}
