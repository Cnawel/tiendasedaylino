<?php
/**
 * ========================================================================
 * LOGIN DIRECTO ADMIN - Bypass del formulario
 * ========================================================================
 * Este script crea la sesión directamente sin pasar por el formulario
 * Útil para diagnóstico y acceso directo
 * ========================================================================
 */

session_start();
require_once 'config/database.php';

// Limpiar intentos fallidos
$_SESSION['login_attempts'] = [];

$email_admin = 'admin@test.com';

// Buscar usuario admin
$stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email_admin);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Crear sesión directamente
    $_SESSION['id_usuario'] = $row['id_usuario'];
    $_SESSION['nombre'] = $row['nombre'];
    $_SESSION['apellido'] = $row['apellido'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['rol'] = strtolower(trim($row['rol'] ?? 'admin'));
    
    // Limpiar intentos fallidos
    unset($_SESSION['login_attempts']);
    
    echo "<h2 style='color: green;'>✓ Sesión de Admin Creada</h2>";
    echo "<p style='color: green; font-weight: bold;'>Has iniciado sesión como administrador.</p>";
    echo "<hr>";
    echo "<pre>";
    echo "ID Usuario: " . $_SESSION['id_usuario'] . "\n";
    echo "Nombre: " . htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) . "\n";
    echo "Email: " . htmlspecialchars($_SESSION['email']) . "\n";
    echo "Rol: " . htmlspecialchars($_SESSION['rol']) . "\n";
    echo "</pre>";
    echo "<hr>";
    echo "<p><a href='admin.php' style='font-size: 20px; font-weight: bold; color: green; text-decoration: none; padding: 10px 20px; background: #e8f5e9; border-radius: 5px; display: inline-block;'>→ Ir al Panel Admin</a></p>";
    echo "<p><a href='login.php'>O volver al Login</a></p>";
    
    // Redirigir automáticamente después de 2 segundos
    echo "<script>setTimeout(function(){ window.location.href='admin.php'; }, 2000);</script>";
    
} else {
    echo "<h2 style='color: red;'>✗ Error</h2>";
    echo "<p>Usuario admin@test.com no encontrado en la base de datos.</p>";
    echo "<p><a href='fix_admin_login.php'>Ejecutar Fix Admin Login</a></p>";
}

$mysqli->close();
?>

