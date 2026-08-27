<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandaloneAssetController extends Controller
{
    public function index(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->isRoot($request)) return $this->forbidden();
        try { return response()->json(['ok' => true, 'data' => $api->standaloneAssets()]); }
        catch (RequestException $e) { return $this->apiError($e, 'No fue posible obtener los assets.'); }
    }

    public function store(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->isRoot($request)) return $this->forbidden();
        $request->validate(['image' => ['required', 'image', 'max:5120']]);
        try { return response()->json(['ok' => true, 'data' => $api->saveStandaloneAsset($request->except(['image', 'mobileImage']), null, $request->file('image'), $request->file('mobileImage')), 'message' => 'Asset creado correctamente.']); }
        catch (RequestException $e) { return $this->apiError($e, 'No fue posible crear el asset.'); }
    }

    public function update(Request $request, int $asset, DashboardApiClient $api): JsonResponse
    {
        if (! $this->isRoot($request)) return $this->forbidden();
        try { return response()->json(['ok' => true, 'data' => $api->saveStandaloneAsset($request->except(['image', 'mobileImage']), $asset, $request->file('image'), $request->file('mobileImage')), 'message' => 'Asset actualizado correctamente.']); }
        catch (RequestException $e) { return $this->apiError($e, 'No fue posible actualizar el asset.'); }
    }

    private function isRoot(Request $request): bool { return in_array('ROOT', DashboardAccess::permissions($request->session()->get('stj.user')), true); }
    private function forbidden(): JsonResponse { return response()->json(['ok' => false, 'message' => 'Solo un usuario ROOT puede acceder a esta gestion.'], 403); }
    private function apiError(RequestException $e, string $fallback): JsonResponse { return response()->json(['ok' => false, 'message' => $e->response?->json('message') ?: $fallback, 'errors' => $e->response?->json('errors') ?: []], $e->response?->status() ?: 502); }
}
