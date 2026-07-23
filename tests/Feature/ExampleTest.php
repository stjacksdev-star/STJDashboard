<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\CasAuthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_cas_token_callback_starts_dashboard_session(): void
    {
        config([
            'stj.cas.signature' => 'test-signature',
            'stj.cas.validate_url' => 'https://cas.stjacks.com/API/validateUser',
            'stj.cas.origin' => 'http://127.0.0.1:8001',
        ]);

        Http::fake([
            'https://cas.stjacks.com/API/validateUser' => Http::response([
                'resultado' => true,
                'cas' => [
                    'exp' => now()->addHour()->timestamp,
                    'data' => [
                        'idUser' => '3',
                        'nombre' => 'Emmanuel Lopez',
                        'correo' => 'emmanuel@stjacks.com',
                        'usuario' => 'emmanuel@stjacks.com',
                        'mydepartment' => '1',
                        'operaciones' => [],
                        'tiendas' => '00000',
                        'pais' => 'SV',
                    ],
                ],
            ]),
        ]);

        $response = $this->get('/login?token=valid-token');

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('stj.user.nombre', 'Emmanuel Lopez')
            ->assertSessionHas('stj.user.bearer', 'valid-token')
            ->assertSessionHas('stj.user.idPais', 1);

        Http::assertSent(fn ($request) =>
            $request->hasHeader('Authorization', 'Bearer valid-token')
            && $request->hasHeader('Signature', 'Token test-signature')
            && $request->hasHeader('STJ', 'Bearer valid-token')
            && $request->hasHeader('Origin', 'http://127.0.0.1:8001')
        );
    }

    public function test_login_callback_without_token_returns_to_login_with_status(): void
    {
        $response = $this->get('/Login');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status.message', 'CAS retorno al dashboard, pero no envio token para validar la sesion.');
    }

    public function test_cas_login_url_only_includes_redirect_when_configured(): void
    {
        config([
            'stj.cas.base_url' => 'https://cas.stjacks.com',
            'stj.cas.signature' => 'test-signature',
            'stj.cas.redirect' => null,
        ]);

        $cas = app(CasAuthService::class);
        parse_str((string) parse_url($cas->loginUrl('https://dashboard.stjacks.com/login'), PHP_URL_QUERY), $productionQuery);

        $this->assertArrayNotHasKey('redirect', $productionQuery);

        config(['stj.cas.redirect' => 'slf']);
        parse_str((string) parse_url($cas->loginUrl('http://127.0.0.1:8001/login'), PHP_URL_QUERY), $localQuery);

        $this->assertSame('slf', $localQuery['redirect'] ?? null);
        $this->assertSame('http://127.0.0.1:8001/login', $localQuery['url'] ?? null);
    }
}
