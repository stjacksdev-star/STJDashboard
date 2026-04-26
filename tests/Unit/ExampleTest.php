<?php

namespace Tests\Unit;

use App\Support\DashboardMenu;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_dashboard_menu_is_filtered_by_operation_permissions(): void
    {
        $menu = DashboardMenu::forUser([
            'operaciones' => [
                ['ope_codigo' => 'MENU_KPI'],
                ['ope_codigo' => 'MENU_PEDIDOS'],
                ['ope_codigo' => 'MENU_COLECCIONES'],
            ],
        ]);

        $labels = collect($menu)
            ->flatMap(fn (array $section) => collect($section['items'])->pluck('label'))
            ->values()
            ->all();

        $this->assertContains('Venta', $labels);
        $this->assertContains('Pendientes', $labels);
        $this->assertContains('Procesados', $labels);
        $this->assertContains('Colecciones', collect($menu)->pluck('label')->all());
        $this->assertNotContains('Promociones', $labels);
    }

    public function test_dashboard_menu_allows_everything_with_root_permission(): void
    {
        $menu = DashboardMenu::forUser([
            'operaciones' => [
                ['ope_codigo' => 'ROOT'],
            ],
        ]);

        $labels = collect($menu)
            ->flatMap(fn (array $section) => collect($section['items'])->pluck('label'))
            ->values()
            ->all();

        $this->assertContains('Promociones', $labels);
        $this->assertContains('Productos', collect($menu)->pluck('label')->all());
        $this->assertContains('Configuracion', collect($menu)->pluck('label')->all());
    }
}
