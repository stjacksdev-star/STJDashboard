<?php

namespace App\Support;

class DashboardMenu
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?array $user): array
    {
        $sections = [
            [
                'label' => "St. Jack's",
                'items' => [
                    self::item('Dashboard', '/', 'home'),
                    self::item('Citas', '/citas', 'tag', 'MENU_CITAS'),
                    self::item('Venta', '/venta', 'chart', 'MENU_KPI'),
                    self::item('Promociones', '/promociones', 'tag', 'MENU_PROMOCIONES'),
                ],
            ],
            [
                'label' => 'Cupones',
                'permission' => 'MENU_CUPONES',
                'items' => [
                    self::item('Mantenimiento', '/cupones/mantenimiento', 'receipt', 'MENU_CUPONES_CRUD'),
                    self::item('Reportes', '/cupones/reportes', 'report', 'MENU_CUPONES_REPORTE'),
                ],
            ],
            [
                'label' => 'Pedidos',
                'items' => [
                    self::item('Gestiones', '/pedidos/gestiones', 'truck', 'MENU_GESTION_PEDIDO'),
                    self::item('Pendientes', '/pedidos/pendientes', 'list', 'MENU_PEDIDOS'),
                    self::item('Devoluciones', '/pedidos/devoluciones', 'refresh', 'MENU_DEVOLUCIONES'),
                    self::group('Busqueda', 'search', [
                        self::item('General', '/pedidos/busqueda'),
                        self::item('Referencia', '/pedidos/consulta'),
                    ]),
                ],
            ],
            [
                'label' => 'Reportes',
                'items' => [
                    self::item('Catalogo', '/reportes/catalogo', 'grid', 'MENU_CATALOGO_PDF'),
                    self::item('Suscriptores', '/reportes/suscriptores', 'users', 'MENU_NEWSLETTER'),
                    self::group('IM', 'layers', [
                        self::item('Venta por pais', '/reportes/im/venta', permission: 'MENU_IM_VENTA'),
                    ], 'MENU_IM'),
                    self::group('Tiendas', 'flag', [
                        self::item('Corte Virtual', '/reportes/corte-virtual', permission: 'MENU_CORTE_VIRTUAL'),
                        self::item('Articulos pendientes', '/reportes/articulos-pendientes', permission: 'MENU_REPO_VENTA'),
                        self::item('Articulos pendientes por pedido', '/reportes/articulos-pendientes-pedido', permission: 'MENU_REPO_VENTA_PEDIDO'),
                    ]),
                    self::group('Contabilidad', 'clipboard', [
                        self::item('Venta general', '/reportes/contabilidad/venta-general', permission: 'MENU_REPO_CONTA_1'),
                        self::item('Venta general 2', '/reportes/contabilidad/venta-general-2', permission: 'MENU_CONTABILIDAD_2'),
                        self::item('Venta general 3', '/reportes/contabilidad/venta-general-3', permission: 'MENU_CONTABILIDAD_3'),
                    ], 'MENU_CONTABILIDAD'),
                ],
            ],
            [
                'label' => 'Productos',
                'permission' => 'MENU_PRODUCTOS',
                'items' => [
                    self::item('Categorias', '/productos/categorias', 'bookmark'),
                    self::item('Maestro', '/productos/catalogo', 'tag'),
                    self::item('Por pais', '/productos/pais', 'flag'),
                ],
            ],
            [
                'label' => 'Colecciones',
                'permission' => 'MENU_COLECCIONES',
                'items' => [
                    self::item('Colecciones', '/colecciones', 'layers'),
                ],
            ],
            [
                'label' => 'Configuracion',
                'permission' => 'MENU_CONFIGURACION',
                'items' => [
                    self::item('LOG', '/configuracion/log', 'activity'),
                    self::group('Componentes', 'settings', [
                        self::item('Slides', '/configuracion/slides', permission: 'MENU_SLIDES'),
                        self::item('Imagenes', '/configuracion/imagenes', permission: 'MENU_IMAGENES'),
                    ]),
                ],
            ],
        ];

        return collect($sections)
            ->map(fn (array $section) => self::filterSection($section, $user))
            ->filter()
            ->values()
            ->all();
    }

    private static function filterSection(array $section, ?array $user): ?array
    {
        if (isset($section['permission']) && ! DashboardAccess::can($user, $section['permission'])) {
            return null;
        }

        $items = collect($section['items'])
            ->map(fn (array $item) => self::filterItem($item, $user))
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'label' => $section['label'],
            'items' => $items,
        ];
    }

    private static function filterItem(array $item, ?array $user): ?array
    {
        if (isset($item['permission']) && ! DashboardAccess::can($user, $item['permission'])) {
            return null;
        }

        if (($item['type'] ?? 'item') === 'group') {
            $children = collect($item['children'])
                ->map(fn (array $child) => self::filterItem($child, $user))
                ->filter()
                ->values()
                ->all();

            if ($children === []) {
                return null;
            }

            $item['children'] = $children;
        }

        unset($item['permission']);

        return $item;
    }

    private static function item(string $label, string $href, string $icon = 'dot', ?string $permission = null): array
    {
        return array_filter([
            'type' => 'item',
            'label' => $label,
            'href' => $href,
            'icon' => $icon,
            'permission' => $permission,
        ]);
    }

    private static function group(string $label, string $icon, array $children, ?string $permission = null): array
    {
        return array_filter([
            'type' => 'group',
            'label' => $label,
            'icon' => $icon,
            'children' => $children,
            'permission' => $permission,
        ]);
    }
}
