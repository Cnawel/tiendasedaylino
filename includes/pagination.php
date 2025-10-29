<?php
/**
 * ========================================================================
 * PAGINACIÓN ESTÁNDAR - Tienda Seda y Lino
 * ========================================================================
 * Componente reutilizable para paginación con Bootstrap 5.
 * 
 * Funciones
 * - render_pagination(int $currentPage, int $totalPages, string $baseUrl, string $paramName = 'page', array $queryParams = []):
 *     Imprime la paginación preservando parámetros de consulta.
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

if (!function_exists('render_pagination')) {
    /**
     * Imprime un componente de paginación Bootstrap 5.
     * - Preserva query params y modifica solo el parámetro de página.
     * - Muestra navegación previa/siguiente y rango alrededor de la actual.
     *
     * @param int    $currentPage   Página actual (>=1)
     * @param int    $totalPages    Páginas totales (>=1)
     * @param string $baseUrl       URL base (ej: 'catalogo.php')
     * @param string $paramName     Nombre del parámetro de página (default 'page')
     * @param array  $queryParams   Otros parámetros a preservar (ej: filtros)
     * @return void
     */
    function render_pagination($currentPage, $totalPages, $baseUrl, $paramName = 'page', $queryParams = []) {
        $currentPage = max(1, (int)$currentPage);
        $totalPages  = max(1, (int)$totalPages);
        if ($totalPages <= 1) return;

        // Construye URL con la página indicada
        $buildUrl = function($page) use ($baseUrl, $paramName, $queryParams) {
            $params = $queryParams;
            $params[$paramName] = (int)$page;
            $qs = http_build_query($params);
            return htmlspecialchars($baseUrl . ($qs ? ('?' . $qs) : ''), ENT_QUOTES, 'UTF-8');
        };

        // Determinar rango compacto (ej: actual ±2)
        $window = 2;
        $start = max(1, $currentPage - $window);
        $end   = min($totalPages, $currentPage + $window);

        echo '<nav aria-label="Paginación">';
        echo '<ul class="pagination">';

        // Botón Anterior
        $prevDisabled = ($currentPage <= 1) ? ' disabled' : '';
        $prevUrl = $buildUrl(max(1, $currentPage - 1));
        echo '<li class="page-item' . $prevDisabled . '">';
        echo '<a class="page-link" href="' . $prevUrl . '" tabindex="-1" aria-label="Anterior">&laquo;</a>';
        echo '</li>';

        // Primera página con elipsis si es necesario
        if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . $buildUrl(1) . '">1</a></li>';
            if ($start > 2) {
                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }

        // Ventana central
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $currentPage) ? ' active' : '';
            echo '<li class="page-item' . $active . '"><a class="page-link" href="' . $buildUrl($i) . '">' . (int)$i . '</a></li>';
        }

        // Última página con elipsis si es necesario
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="' . $buildUrl($totalPages) . '">' . (int)$totalPages . '</a></li>';
        }

        // Botón Siguiente
        $nextDisabled = ($currentPage >= $totalPages) ? ' disabled' : '';
        $nextUrl = $buildUrl(min($totalPages, $currentPage + 1));
        echo '<li class="page-item' . $nextDisabled . '">';
        echo '<a class="page-link" href="' . $nextUrl . '" aria-label="Siguiente">&raquo;</a>';
        echo '</li>';

        echo '</ul>';
        echo '</nav>';
    }
}


