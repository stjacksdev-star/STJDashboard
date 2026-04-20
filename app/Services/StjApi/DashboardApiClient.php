<?php

namespace App\Services\StjApi;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class DashboardApiClient
{
    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function collections(?string $country = null): array
    {
        $response = Http::baseUrl(rtrim((string) config('stj.api.base_url'), '/'))
            ->timeout((int) config('stj.api.timeout'))
            ->withToken((string) config('stj.api.dashboard_token'))
            ->acceptJson()
            ->get('/dashboard/collections', array_filter([
                'country' => $country,
                'limit' => 300,
            ], fn ($value) => filled($value)));

        $response->throw();

        return $response->json('data') ?? [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createCollection(array $data, UploadedFile $banner, UploadedFile $products): array
    {
        $response = Http::baseUrl(rtrim((string) config('stj.api.base_url'), '/'))
            ->timeout((int) config('stj.api.timeout'))
            ->withToken((string) config('stj.api.dashboard_token'))
            ->acceptJson()
            ->attach('banner', fopen($banner->getRealPath(), 'rb'), $banner->getClientOriginalName())
            ->attach('products', fopen($products->getRealPath(), 'rb'), $products->getClientOriginalName())
            ->post('/dashboard/collections', [
                'country' => $data['country'],
                'name' => $data['name'],
                'title' => $data['title'],
                'mobilePosition' => $data['mobilePosition'] ?? 'right',
            ]);

        $response->throw();

        return $response->json('data') ?? [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function updateCollection(int $id, array $data, ?UploadedFile $banner = null, ?UploadedFile $products = null): array
    {
        $request = Http::baseUrl(rtrim((string) config('stj.api.base_url'), '/'))
            ->timeout((int) config('stj.api.timeout'))
            ->withToken((string) config('stj.api.dashboard_token'))
            ->acceptJson();

        if ($banner) {
            $request = $request->attach('banner', fopen($banner->getRealPath(), 'rb'), $banner->getClientOriginalName());
        }

        if ($products) {
            $request = $request->attach('products', fopen($products->getRealPath(), 'rb'), $products->getClientOriginalName());
        }

        $response = $request->post("/dashboard/collections/{$id}", [
            'country' => $data['country'],
            'name' => $data['name'],
            'title' => $data['title'],
            'mobilePosition' => $data['mobilePosition'] ?? 'right',
        ]);

        $response->throw();

        return $response->json('data') ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function collectionAssets(int $collection): array
    {
        $response = Http::baseUrl(rtrim((string) config('stj.api.base_url'), '/'))
            ->timeout((int) config('stj.api.timeout'))
            ->withToken((string) config('stj.api.dashboard_token'))
            ->acceptJson()
            ->get("/dashboard/collections/{$collection}/assets");

        $response->throw();

        return $response->json('data') ?? [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createCollectionAsset(int $collection, array $data, UploadedFile $image, ?UploadedFile $mobileImage = null): array
    {
        $request = Http::baseUrl(rtrim((string) config('stj.api.base_url'), '/'))
            ->timeout((int) config('stj.api.timeout'))
            ->withToken((string) config('stj.api.dashboard_token'))
            ->acceptJson()
            ->attach('image', fopen($image->getRealPath(), 'rb'), $image->getClientOriginalName());

        if ($mobileImage) {
            $request = $request->attach('mobileImage', fopen($mobileImage->getRealPath(), 'rb'), $mobileImage->getClientOriginalName());
        }

        $response = $request->post("/dashboard/collections/{$collection}/assets", [
            'type' => $data['type'],
            'platform' => $data['platform'] ?? 'WEB',
            'position' => $data['position'] ?? null,
            'order' => $data['order'] ?? 1,
            'status' => $data['status'] ?? 'PENDIENTE',
            'startAt' => $data['startAt'],
            'endAt' => $data['endAt'],
            'title' => $data['title'] ?? null,
        ]);

        $response->throw();

        return $response->json('data') ?? [];
    }
}
