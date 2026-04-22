<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function showByReference(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'reference' => ['required', 'string', 'max:60'],
        ]);

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

    public function product(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:3'],
            'sku' => ['required', 'string', 'max:60'],
            'size' => ['nullable', 'string', 'max:20'],
        ]);

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

    public function process(Request $request, DashboardApiClient $api): JsonResponse
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
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->processOrder(
                    $validated['country'],
                    $validated['reference'],
                    $validated['ticket'],
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
            'permissions' => $user['operaciones'] ?? [],
            'ip' => $request->ip(),
            'userAgent' => substr((string) $request->userAgent(), 0, 500),
        ];
    }
}
