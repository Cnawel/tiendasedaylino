<?php
/**
 * ========================================================================
 * CONFIGURACIÓN DE BASE DE DATOS - Tienda Seda y Lino
 * ========================================================================
 * Archivo de configuración centralizado para la conexión a MySQL
 * 
 * Base de Datos: tiendasedaylino_db
 * Estructura: Definida en sql/database_estructura.sql
 * 
 * Tablas principales:
 * - Usuarios: Gestión de clientes y usuarios del sistema
 * - Productos: Catálogo de productos
 * - Categorias: Clasificación de productos
 * - Stock_Variantes: Control de inventario por talle y color
 * - Pedidos, Pagos, etc.
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

// Credenciales de conexión a la base de datos
$host = 'localhost';
$dbname = 'tiendasedaylino_db';  // Base de datos principal
$username = 'root';               // Usuario de MySQL
$password = '';                   // Contraseña de MySQL

try {
    // Crear conexión PDO con manejo de errores y charset UTF-8
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configurar modo de errores para lanzar excepciones
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Establecer charset UTF-8 para compatibilidad con caracteres especiales
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
} catch(PDOException $e) {
    // Manejo de errores de conexión
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// ========================================================================
// CONEXIÓN MYSQLI - Para compatibilidad con código existente
// ========================================================================
$mysqli = new mysqli($host, $username, $password, $dbname);

// Verificar conexión MySQLi
if ($mysqli->connect_errno) {
    die('Error de conexión a la base de datos (MySQLi): ' . $mysqli->connect_error);
}

// Establecer charset UTF-8 (utf8mb4 para soporte completo de caracteres especiales)
$mysqli->set_charset("utf8mb4");

// Establecer collation explícitamente a utf8mb4_unicode_ci para toda la conexión
$mysqli->query("SET collation_connection = 'utf8mb4_unicode_ci'");
$mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
// ========================================================================

