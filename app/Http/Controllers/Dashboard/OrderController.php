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

class OrderController extends Controller
{
    private const PROCESSED_ORDER_STATUSES = [
        'PREPARADO',
        'EMPACADO-ENTREGA',
        'EN-RUTA',
        'ENTREGADO',
        'ANULADO-ERROR',
        'ANULADO-PRUEBA',
        'ANULADO-CLIENTE',
        'ANULADO-INVENTARIO',
        'DEVOLUCION',
        'ANULADO-EFECTIVO',
    ];

    public function refunds(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_DEVOLUCIONES')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para ver devoluciones.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'store' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:SI,NO'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);
        $canUseGlobalFilters = $this->canUseGlobalFilters($permissions);
        $hasAssignedStore = filled($user['storeCode'] ?? null)
            || (filled($user['tiendas'] ?? null) && (string) $user['tiendas'] !== '00000');

        if ($hasAssignedStore && ! $canUseGlobalFilters) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeCode'] ?? $user['tiendas']);
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->orderRefunds($validated),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener las devoluciones desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function refundPdf(Request $request, int $order, DashboardApiClient $api, UserCountryAccessService $countryAccess): Response|JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_DEVOLUCIONES')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para generar comprobantes de devolucion.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'store' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:SI,NO'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);
        $canUseGlobalFilters = $this->canUseGlobalFilters($permissions);
        $hasAssignedStore = filled($user['storeCode'] ?? null)
            || (filled($user['tiendas'] ?? null) && (string) $user['tiendas'] !== '00000');

        if ($hasAssignedStore && ! $canUseGlobalFilters) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeCode'] ?? $user['tiendas']);
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        $validated['status'] = 'SI';

        try {
            $payload = $api->orderRefunds($validated);
            $refund = collect($payload['refunds'] ?? [])
                ->first(fn (array $item) => (int) ($item['id'] ?? 0) === $order);

            if (! $refund) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se encontro la devolucion seleccionada.',
                ], 404);
            }

            if (($refund['serviceApproved'] ?? null) !== true) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La devolucion no fue aprobada por el servicio.',
                ], 422);
            }

            $html = view('orders.refund-pdf', [
                'refund' => $refund,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ])->render();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);

            $pdf = new Dompdf($options);
            $pdf->setPaper('letter', 'portrait');
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->render();

            $reference = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($refund['ref'] ?? $order));

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="devolucion-'.$reference.'.pdf"',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible generar el comprobante de devolucion desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function paymentAttempts(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PEDIDOS')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para ver intentos de pago.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'order' => ['required', 'integer', 'min:1'],
            'store' => ['nullable', 'string', 'max:20'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);
        $canUseGlobalFilters = $this->canUseGlobalFilters($permissions);
        $hasAssignedStore = filled($user['storeCode'] ?? null)
            || (filled($user['tiendas'] ?? null) && (string) $user['tiendas'] !== '00000');

        if ($hasAssignedStore && ! $canUseGlobalFilters) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeCode'] ?? $user['tiendas']);
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->orderPaymentAttempts($validated),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener los intentos de pago desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function search(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PEDIDOS')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para buscar pedidos.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'query' => ['required', 'string', 'min:2', 'max:120'],
            'store' => ['nullable', 'string', 'max:20'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $actor = $this->actor($request);
        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);
        $canUseGlobalFilters = $this->canUseGlobalFilters($permissions);
        $hasAssignedStore = filled($user['storeCode'] ?? null)
            || (filled($user['tiendas'] ?? null) && (string) $user['tiendas'] !== '00000');

        if ($hasAssignedStore && ! $canUseGlobalFilters) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
            $validated['store'] = (string) ($user['storeCode'] ?? $user['tiendas']);
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->searchOrders($validated, $actor),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible buscar pedidos desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function showByReference(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'reference' => ['required', 'string', 'max:60'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->orderByReference($validated['country'], $validated['reference']),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible obtener el pedido desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function processedPdf(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): Response|JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PEDIDOS')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para generar comprobantes de pedidos.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'reference' => ['required', 'string', 'max:60'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);
        $permissions = DashboardAccess::permissions($user);
        $canUseGlobalFilters = $this->canUseGlobalFilters($permissions);
        $hasAssignedStore = filled($user['storeCode'] ?? null)
            || (filled($user['tiendas'] ?? null) && (string) $user['tiendas'] !== '00000');

        if ($hasAssignedStore && ! $canUseGlobalFilters) {
            $validated['country'] = (string) ($user['idPais'] ?? $validated['country']);
        }

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            $payload = $api->orderByReference($validated['country'], $validated['reference']);
            $order = $payload['order'] ?? [];
            $products = $payload['products'] ?? [];
            $status = strtoupper((string) ($order['status'] ?? ''));

            if (! in_array($status, self::PROCESSED_ORDER_STATUSES, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El pedido seleccionado no pertenece al listado de procesados.',
                ], 422);
            }

            if ($hasAssignedStore && ! $canUseGlobalFilters) {
                $sessionStore = $this->normalizeStoreCode((string) ($user['storeCode'] ?? $user['tiendas'] ?? ''));
                $orderStore = $this->normalizeStoreCode((string) data_get($order, 'storePickup.storeCode', ''));

                if ($sessionStore === '' || $orderStore === '' || $sessionStore !== $orderStore) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'No tiene permiso para generar comprobantes de esta tienda.',
                    ], 403);
                }
            }

            $html = view('orders.processed-pdf', [
                'order' => $order,
                'products' => $products,
                'currency' => $this->currencyForCountry((int) ($order['countryId'] ?? $validated['country'])),
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ])->render();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);

            $pdf = new Dompdf($options);
            $pdf->setPaper('letter', 'portrait');
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->render();

            $reference = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($order['reference'] ?? $validated['reference']));

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="pedido-'.$reference.'.pdf"',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible generar el comprobante de pedido desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function product(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'sku' => ['required', 'string', 'max:60'],
            'size' => ['nullable', 'string', 'max:20'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->validateOrderProduct($validated['country'], $validated['sku'], $validated['size'] ?? null),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible validar el articulo desde stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function updateLine(Request $request, int $line, DashboardApiClient $api): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PROCESAR_PEDIDO')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para editar articulos del pedido.',
            ], 403);
        }

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:60'],
            'size' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updateOrderLine($line, $validated, $this->actor($request)),
                'message' => 'Articulo actualizado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible actualizar el articulo en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function shippingManagement(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->isRoot($request)) {
            return response()->json(['ok' => false, 'message' => 'Solo un usuario ROOT puede acceder a esta gestion.'], 403);
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:60'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->shippingManagement($validated['reference'], $this->actor($request)),
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible consultar el pedido.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function updateShippingManagement(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->isRoot($request)) {
            return response()->json(['ok' => false, 'message' => 'Solo un usuario ROOT puede acceder a esta gestion.'], 403);
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:60'],
            'shippingType' => ['required', 'string', 'max:50'],
            'urbanId' => ['nullable', 'string', 'max:100'],
            'shippingId' => ['nullable', 'string', 'max:100'],
            'shippingCost' => ['required', 'numeric', 'min:0'],
            'shippingCostText' => ['nullable', 'string', 'max:200'],
            'finalShippingCost' => ['required', 'numeric', 'min:0'],
            'freeShipping' => ['required', 'in:SI,NO'],
            'routeAt' => ['nullable', 'date'],
            'addressType' => ['nullable', 'string', 'max:30'],
            'samePerson' => ['required', 'in:SI,NO'],
            'sameAddress' => ['required', 'in:SI,NO'],
            'country' => ['required', 'string', 'max:10'],
            'latitude' => ['nullable', 'string', 'max:50'],
            'longitude' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:200'],
            'referencePoint' => ['nullable', 'string', 'max:200'],
            'departmentId' => ['nullable', 'string', 'max:30'],
            'municipalityId' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'receiverName' => ['nullable', 'string', 'max:100'],
            'receiverPhone' => ['nullable', 'string', 'max:100'],
            'saveType' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updateShippingManagement($validated, $this->actor($request)),
                'message' => 'Datos de envio actualizados correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible actualizar los datos de envio.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function updateData(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'reference' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'billingAddress' => ['required', 'string', 'max:200'],
            'shippingAddress' => ['nullable', 'string', 'max:200'],
            'shippingReference' => ['nullable', 'string', 'max:200'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updateOrderData($validated, $this->actor($request)),
                'message' => 'Datos del pedido actualizados correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible actualizar los datos del pedido en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function process(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PROCESAR_PEDIDO')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para procesar pedidos.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'reference' => ['required', 'string', 'max:60'],
            'ticket' => ['required', 'string', 'max:100'],
            'refundObservation' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->processOrder(
                    $validated['country'],
                    $validated['reference'],
                    $validated['ticket'],
                    $validated['refundObservation'] ?? null,
                    $this->actor($request),
                ),
                'message' => 'Pedido procesado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible procesar el pedido en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function deliver(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PEDIDOS')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para entregar pedidos.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'reference' => ['required', 'string', 'max:60'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->deliverOrder(
                    $validated['country'],
                    $validated['reference'],
                    $this->actor($request),
                ),
                'message' => 'Pedido entregado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible entregar el pedido en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function markPackedForPickup(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PROCESAR_PEDIDO')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para preparar pedidos.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'reference' => ['required', 'string', 'max:60'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->markOrderPackedForPickup(
                    $validated['country'],
                    $validated['reference'],
                    $this->actor($request),
                ),
                'message' => 'Pedido marcado como preparado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible marcar el pedido como preparado en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    public function markInRoute(Request $request, DashboardApiClient $api, UserCountryAccessService $countryAccess): JsonResponse
    {
        if (! DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PEDIDOS')) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para marcar pedidos en ruta.',
            ], 403);
        }

        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'reference' => ['required', 'string', 'max:60'],
        ]);

        $user = (array) $request->session()->get('stj.user', []);

        if (! $countryAccess->canAccessCountry($user, $validated['country'])) {
            return $this->countryForbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->markOrderInRoute(
                    $validated['country'],
                    $validated['reference'],
                    $this->actor($request),
                ),
                'message' => 'Pedido marcado en ruta correctamente.',
            ]);
        } catch (RequestException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->response?->json('message') ?: 'No fue posible marcar el pedido en ruta en stj-api.',
                'errors' => $exception->response?->json('errors') ?: [],
            ], $exception->response?->status() ?: 502);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(Request $request): array
    {
        $user = (array) $request->session()->get('stj.user', []);

        return [
            'id' => $user['idUser'] ?? $user['id'] ?? null,
            'name' => $user['nombre'] ?? $user['name'] ?? null,
            'email' => $user['correo'] ?? $user['email'] ?? null,
            'username' => $user['usuario'] ?? $user['username'] ?? null,
            'countryId' => $user['idPais'] ?? null,
            'storeId' => $user['storeId'] ?? $user['tiendas'] ?? null,
            'storeCode' => $user['storeCode'] ?? $user['tiendas'] ?? null,
            'permissions' => DashboardAccess::permissions($user),
            'ip' => $request->ip(),
            'userAgent' => substr((string) $request->userAgent(), 0, 500),
        ];
    }

    private function isRoot(Request $request): bool
    {
        return in_array('ROOT', DashboardAccess::permissions($request->session()->get('stj.user')), true);
    }

    /**
     * @param array<int, string> $permissions
     */
    private function canUseGlobalFilters(array $permissions): bool
    {
        return in_array('ROOT', $permissions, true)
            || in_array('INDICADORES_GENERICOS', $permissions, true);
    }

    private function normalizeStoreCode(string $value): string
    {
        $value = trim($value);

        return is_numeric($value) ? ltrim($value, '0') : $value;
    }

    private function currencyForCountry(int $country): string
    {
        return match ($country) {
            2 => 'Q',
            3 => 'CRC',
            7 => 'L',
            8 => 'USD',
            default => 'USD',
        };
    }

    private function countryForbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para consultar este pais.',
        ], 403);
    }
}
