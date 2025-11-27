<?php
/**
 * ========================================================================
 * GESTIÓN DE TABLAS SQL - Tienda Seda y Lino
 * ========================================================================
 * Punto de entrada para la gestión de tablas de la base de datos
 * Solo accesible para administradores
 * 
 * FUNCIONALIDADES:
 * - Lista todas las tablas de la base de datos
 * - Permite visualizar, editar y eliminar registros de cualquier tabla
 * 
 * ACCESO: Solo usuarios con rol 'admin' (mediante requireAdmin())
 * ========================================================================
 */
session_start();

// Cargar sistema de autenticación centralizado
require_once __DIR__ . '/includes/auth_check.php';

// Verificar que el usuario esté logueado y sea admin
requireAdmin();

// Redirigir a la página de gestión de tablas SQL
header('Location: sql/tablas_sql.php');
exit;

