<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Services\UserCountryAccessService;
use App\Support\DashboardAccess;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StoreReportController extends Controller
{
    public function catalog(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_CORTE_VIRTUAL')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para ver corte virtual.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:3'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);

        if (! $this->canUseGlobalFilters($permissions)) {
            $validated['country'] = (string) ($user['idPais'] ?? '');
        }

        if (filled($validated['country'] ?? null) && ! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            $data = $api->storeReportCatalog($validated['country'] ?? null);
            $data['countries'] = $countryAccess->filterCountries($user, $data['countries'] ?? []);

            return response()->json([
                'ok' => true,
                'data' => $data,
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible cargar el catalogo desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function virtualCut(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_CORTE_VIRTUAL')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para generar corte virtual.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'store' => ['nullable', 'string', 'max:20'],
            'date' => ['required', 'date'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);

        if (! $this->canUseGlobalFilters($permissions)) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeCode'] ?? $user['tiendas'] ?? '');
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->storeVirtualCut($validated),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible generar el corte virtual desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function virtualCutPdf(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): Response|JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_CORTE_VIRTUAL')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para generar corte virtual.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'store' => ['nullable', 'string', 'max:20'],
            'date' => ['required', 'date'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);

        if (! $this->canUseGlobalFilters($permissions)) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeCode'] ?? $user['tiendas'] ?? '');
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            $report = $api->storeVirtualCut($validated);
            $html = view('reports.store-virtual-cut-pdf', [
                'report' => $report,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ])->render();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);

            $pdf = new Dompdf($options);
            $pdf->setPaper('letter', 'landscape');
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->render();

            $filename = sprintf(
                'corte-virtual-%s-%s.pdf',
                $report['filters']['store'] ?? 'tienda',
                $report['filters']['date'] ?? $validated['date'],
            );

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible generar el PDF desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function pendingItems(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_REPO_VENTA')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para ver articulos pendientes.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'store' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', 'in:DOMICILIO,TIENDA'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);

        if (! $this->canUseGlobalFilters($permissions)) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeCode'] ?? $user['tiendas'] ?? '');
            $validated['type'] = 'TIENDA';
        }

        $validated['type'] ??= 'TIENDA';

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->storePendingItems($validated),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible cargar los articulos pendientes desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function pendingItemsByOrder(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_REPO_VENTA_PEDIDO')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para ver articulos pendientes por pedido.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);

        if (! $this->canUseGlobalFilters($permissions)) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeCode'] ?? $user['tiendas'] ?? '');
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->storePendingItemsByOrder($validated),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible cargar los articulos pendientes por pedido desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    /**
     * @param array<int, string> $permissions
     */
    private function canUseGlobalFilters(array $permissions): bool
    {
        return in_array('ROOT', $permissions, true)
            || in_array('INDICADORES_GENERICOS', $permissions, true);
    }

    private function countryForbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para consultar reportes de este pais.',
        ], 403);
    }
}
