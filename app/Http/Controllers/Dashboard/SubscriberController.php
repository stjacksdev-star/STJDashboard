<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use App\Support\DashboardAccess;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canUseSubscribers($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:5'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->subscribers($validated['country'] ?? null),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener los suscriptores desde stj-api.');
        }
    }

    public function store(Request $request, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canUseSubscribers($request)) {
            return $this->forbidden();
        }

        $validated = $this->validateSubscriber($request);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->createSubscriber($validated, $this->actor($request)),
                'message' => 'Suscriptor creado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible crear el suscriptor en stj-api.');
        }
    }

    public function update(Request $request, int $subscriber, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canUseSubscribers($request)) {
            return $this->forbidden();
        }

        $validated = $this->validateSubscriber($request);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updateSubscriber($subscriber, $validated, $this->actor($request)),
                'message' => 'Suscriptor actualizado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible actualizar el suscriptor en stj-api.');
        }
    }

    public function destroy(Request $request, int $subscriber, DashboardApiClient $api): JsonResponse
    {
        if (! $this->canUseSubscribers($request)) {
            return $this->forbidden();
        }

        try {
            $api->deleteSubscriber($subscriber);

            return response()->json([
                'ok' => true,
                'message' => 'Suscriptor eliminado correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible eliminar el suscriptor en stj-api.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubscriber(Request $request): array
    {
        return $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'country' => ['required', 'string', 'max:5'],
            'subscribedAt' => ['nullable', 'date'],
        ]);
    }

    private function canUseSubscribers(Request $request): bool
    {
        return DashboardAccess::can($request->session()->get('stj.user'), 'MENU_NEWSLETTER');
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'No tiene permiso para administrar suscriptores.',
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
