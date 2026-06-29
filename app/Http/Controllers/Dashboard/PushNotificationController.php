<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushNotificationController extends Controller
{
    public function index(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canUsePushNotifications($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['TODO', 'PENDIENTE', 'ENVIADO', 'ERROR', 'CANCELADO'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->pushNotifications($validated, $this->actor($request)),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener las notificaciones push desde stj-api.');
        }
    }

    public function store(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canUsePushNotifications($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:5120'],
            'action' => ['required', 'string', 'max:500'],
            'to' => ['nullable', 'string', 'max:500'],
            'platform' => ['required', 'string', Rule::in(['Todo', 'Android', 'Ios'])],
            'scheduledAt' => ['required', 'date'],
            'promotionId' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->createPushNotification($validated, $this->actor($request), $request->file('image')),
                'message' => 'Notificacion push programada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible programar la notificacion push en stj-api.');
        }
    }

    public function destroy(Request $request, int $notification, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canUsePushNotifications($request)) {
            return $this->forbidden();
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->cancelPushNotification($notification, $this->actor($request)),
                'message' => 'Notificacion push cancelada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible cancelar la notificacion push en stj-api.');
        }
    }

    private function canUsePushNotifications(Request $request): bool
    {
        return DashboardAccess::can($request->session()->get('stj.user'), 'MENU_PUSH_NOTIFICACIONES');
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tienes permiso para administrar notificaciones push.',
        ], 403);
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
            'permissions' => DashboardAccess::permissions($user),
            'ip' => $request->ip(),
            'userAgent' => substr((string) $request->userAgent(), 0, 500),
        ];
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
