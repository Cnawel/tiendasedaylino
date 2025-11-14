<?php
/**
 * ========================================================================
 * MIGAS DE PAN (Breadcrumbs) - Tienda Seda y Lino
 * ========================================================================
 * Componente reutilizable para mostrar la jerarquía de navegación.
 * 
 * Funciones
 * - render_breadcrumbs(array $items): imprime un breadcrumb Bootstrap 5.
 *   Cada item debe ser un array con claves:
 *     - label (string, requerido)
 *     - url (string|null, opcional; si null o vacío => item activo)
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

if (!function_exists('render_breadcrumbs')) {
    /**
     * Imprime un breadcrumb accesible con Bootstrap 5.
     *
     * @param array $items Lista de items [ ['label' => 'Inicio', 'url' => 'index.php'], ... ]
     * @return void
     */
    function render_breadcrumbs($items) {
        if (!is_array($items) || empty($items)) {
            return;
        }

        echo '<nav aria-label="breadcrumb" class="mb-3">';
        echo '<ol class="breadcrumb">';

        $lastIndex = count($items) - 1;
        foreach ($items as $index => $item) {
            $label = isset($item['label']) ? htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') : '';
            $url   = isset($item['url']) ? (string)$item['url'] : '';
            $isActive = ($index === $lastIndex) || empty($url);

            if ($isActive) {
                echo '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
            } else {
                $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                echo '<li class="breadcrumb-item"><a href="' . $safeUrl . '">' . $label . '</a></li>';
            }
        }

        echo '</ol>';
        echo '</nav>';
    }
}


