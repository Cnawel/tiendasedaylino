<?php

/**
 * Dashboard Helper Functions
 * Centralized functions for common dashboard operations
 */

/**
 * Get tipos de movimiento mapping with display info
 * @return array Mapping of tipo_movimiento codes to display info
 */
function obtenerTiposMovimientoMap() {
    return [
        'venta' => ['color' => 'success', 'nombre' => 'Venta', 'signo' => '-'],
        'ingreso' => ['color' => 'info', 'nombre' => 'Ingreso', 'signo' => '+'],
        'ajuste' => ['color' => 'warning', 'nombre' => 'Ajuste', 'signo' => ''],
    ];
}
