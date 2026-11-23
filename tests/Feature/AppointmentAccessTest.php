<?php

namespace Tests\Feature;

use App\Services\StjApi\DashboardApiClient;
use App\Services\UserCountryAccessService;
use Illuminate\Http\Client\Response;
use Mockery;
use Tests\TestCase;

class AppointmentAccessTest extends TestCase
{
    public function test_supervisor_puede_solicitar_todas_las_tiendas_de_un_pais_asignado(): void
    {
        $api = Mockery::mock(DashboardApiClient::class);
        $api->shouldReceive('appointments')
            ->once()
            ->with(['country' => '1', 'store' => 'ALL'])
            ->andReturn($this->emptyReport());
        $this->app->instance(DashboardApiClient::class, $api);

        $access = Mockery::mock(UserCountryAccessService::class);
        $access->shouldReceive('canAccessCountry')->once()->with(Mockery::type('array'), '1')->andReturnTrue();
        $access->shouldReceive('allowedCountries')->andReturn([]);
        $this->app->instance(UserCountryAccessService::class, $access);

        $this->withSession($this->sessionData([
            'MENU_CITAS',
            'INDICADORES_GENERICOS',
        ]))->getJson('/dashboard-api/appointments?country=1&store=ALL')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_usuario_de_tienda_no_puede_ampliar_la_consulta_con_all(): void
    {
        $api = Mockery::mock(DashboardApiClient::class);
        $api->shouldReceive('appointments')
            ->once()
            ->with(['country' => '1', 'store' => '24'])
            ->andReturn($this->emptyReport());
        $this->app->instance(DashboardApiClient::class, $api);

        $access = Mockery::mock(UserCountryAccessService::class);
        $access->shouldReceive('canAccessCountry')->once()->with(Mockery::type('array'), '1')->andReturnTrue();
        $access->shouldReceive('allowedCountries')->andReturn([]);
        $this->app->instance(UserCountryAccessService::class, $access);

        $this->withSession($this->sessionData(['MENU_CITAS']))
            ->getJson('/dashboard-api/appointments?country=2&store=ALL')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_exportacion_de_usuario_de_tienda_fuerza_su_tienda_asignada(): void
    {
        $remote = Mockery::mock(Response::class);
        $remote->shouldReceive('body')->once()->andReturn('xlsx-content');
        $remote->shouldReceive('header')->with('Content-Type')->once()->andReturn('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $remote->shouldReceive('header')->with('Content-Disposition')->once()->andReturn('attachment; filename="citas.xlsx"');

        $api = Mockery::mock(DashboardApiClient::class);
        $api->shouldReceive('exportAppointments')
            ->once()
            ->with(['country' => '1', 'store' => '24'])
            ->andReturn($remote);
        $this->app->instance(DashboardApiClient::class, $api);

        $access = Mockery::mock(UserCountryAccessService::class);
        $access->shouldReceive('canAccessCountry')->once()->with(Mockery::type('array'), '1')->andReturnTrue();
        $access->shouldReceive('allowedCountries')->andReturn([]);
        $this->app->instance(UserCountryAccessService::class, $access);

        $this->withSession($this->sessionData(['MENU_CITAS']))
            ->get('/dashboard-api/appointments/export?country=2&store=ALL')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="citas.xlsx"')
            ->assertContent('xlsx-content');
    }

    /** @param array<int, string> $operations */
    private function sessionData(array $operations): array
    {
        return [
            'stj.user' => [
                'idUser' => 10,
                'idPais' => 1,
                'tiendas' => '24',
                'storeId' => 24,
                'storeCode' => '024',
                'storeLabel' => 'Tienda 024',
                'operaciones' => $operations,
            ],
            'stj.expires_at' => now()->addHour()->timestamp,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyReport(): array
    {
        return [
            'filters' => [],
            'summary' => ['appointments' => 0, 'emailsSent' => 0],
            'rows' => [],
        ];
    }
}
