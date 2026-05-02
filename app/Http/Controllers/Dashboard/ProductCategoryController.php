<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StjApi\DashboardApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index(DashboardApiClient $api): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $api->productCategories(),
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible obtener las categorias desde stj-api.');
        }
    }

    public function store(Request $request, DashboardApiClient $api): JsonResponse
    {
        $validated = $this->validateCategory($request);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->createProductCategory($validated, $this->actor($request)),
                'message' => 'Categoria creada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible crear la categoria en stj-api.');
        }
    }

    public function update(Request $request, int $category, DashboardApiClient $api): JsonResponse
    {
        $validated = $this->validateCategory($request);

        try {
            return response()->json([
                'ok' => true,
                'data' => $api->updateProductCategory($category, $validated, $this->actor($request)),
                'message' => 'Categoria actualizada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible actualizar la categoria en stj-api.');
        }
    }

    public function destroy(int $category, DashboardApiClient $api): JsonResponse
    {
        try {
            $api->deleteProductCategory($category);

            return response()->json([
                'ok' => true,
                'message' => 'Categoria eliminada correctamente.',
            ]);
        } catch (RequestException $exception) {
            return $this->apiError($exception, 'No fue posible eliminar la categoria en stj-api.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request): array
    {
        return $request->validate([
            'order' => ['nullable', 'integer', 'min:0'],
            'appOrder' => ['nullable', 'integer', 'min:0'],
            'code' => ['required', 'string', 'max:25'],
            'name' => ['required', 'string', 'max:100'],
            'align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'header' => ['nullable', 'string', 'max:100'],
            'logo' => ['required', 'string', 'max:100'],
            'appName' => ['nullable', 'string', 'max:100'],
            'appLogo' => ['required', 'string', 'max:100'],
            'hasOtherSubcategories' => ['nullable', 'boolean'],
            'otherSubcategories' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'sizes' => ['required', 'string', 'max:250'],
            'brand' => ['nullable', Rule::in(['ST JACKS', 'BUNGEE', 'BASICS', 'JACK & CO'])],
            'enabledSv' => ['nullable', 'boolean'],
            'enabledGt' => ['nullable', 'boolean'],
            'enabledCr' => ['nullable', 'boolean'],
            'enabledNi' => ['nullable', 'boolean'],
            'enabledApp' => ['nullable', 'boolean'],
        ]);
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
