<?php
/**
 * ========================================================================
 * CONFIGURACIÓN DE TALLES - Tienda Seda y Lino
 * ========================================================================
 * Archivo centralizado para definir los talles disponibles en todo el sitio
 * 
 * Este archivo debe ser la única fuente de verdad para los talles.
 * Cualquier cambio en los talles debe hacerse aquí.
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

/**
 * TALLES ESTÁNDAR DISPONIBLES
 * Orden: S, M, L, XL
 */
define('TALLES_ESTANDAR', ['S', 'M', 'L', 'XL']);

/**
 * Obtener los talles estándar disponibles
 * 
 * @return array Array con los talles estándar en orden
 */
function obtenerTallesEstandar() {
    return TALLES_ESTANDAR;
}

/**
 * Obtener el orden de talles estándar
 * Útil para ordenar talles en listas y dropdowns
 * 
 * @return array Array con los talles estándar en orden
 */
function obtenerOrdenTalles() {
    return TALLES_ESTANDAR;
}

/**
 * Verificar si un talle es válido según los estándar
 * 
 * @param string $talle Talle a verificar
 * @return bool true si es un talle válido
 */
function esTalleValido($talle) {
    return in_array(strtoupper($talle), array_map('strtoupper', TALLES_ESTANDAR));
}

