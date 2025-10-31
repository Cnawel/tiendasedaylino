<?php
/**
 * ========================================================================
 * RECREAR USUARIO ADMIN - Eliminar y crear de nuevo
 * ========================================================================
 * Este script elimina completamente el usuario admin@test.com
 * y lo recrea con la contraseña correctamente hasheada
 * ========================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

$email_admin = 'admin@test.com';
$password_admin = 'admin@test.com';
$nombre_admin = 'Administrador';
$apellido_admin = 'Sistema';

echo "<h2>Recrear Usuario Admin</h2>";
echo "<hr>";

// Paso 1: Eliminar usuario existente
echo "<h3>Paso 1: Eliminando usuario existente...</h3>";

// Primero desasociar movimientos de stock si existen
$stmt_desasociar = $mysqli->prepare("UPDATE Movimientos_Stock SET id_usuario = NULL WHERE id_usuario IN (SELECT id_usuario FROM (SELECT id_usuario FROM Usuarios WHERE email = ?) AS temp)");
$stmt_desasociar->bind_param('s', $email_admin);
$stmt_desasociar->execute();

// Eliminar usuario
$stmt_delete = $mysqli->prepare("DELETE FROM Usuarios WHERE email = ?");
$stmt_delete->bind_param('s', $email_admin);
$stmt_delete->execute();

$deleted_rows = $stmt_delete->affected_rows;
if ($deleted_rows > 0) {
    echo "<p style='color: green;'>✓ Usuario eliminado (se eliminaron $deleted_rows registro(s))</p>";
} else {
    echo "<p style='color: orange;'>⚠ Usuario no existía o ya fue eliminado</p>";
}

// Paso 2: Generar hash de contraseña
echo "<h3>Paso 2: Generando hash de contraseña...</h3>";
$password_hash = password_hash($password_admin, PASSWORD_BCRYPT);

if ($password_hash === false) {
    die("<p style='color: red;'>✗ Error: No se pudo generar el hash de la contraseña.</p>");
}

echo "<p style='color: green;'>✓ Hash generado correctamente</p>";
echo "<pre>Hash: " . htmlspecialchars($password_hash) . "</pre>";

// Paso 3: Crear nuevo usuario
echo "<h3>Paso 3: Creando nuevo usuario admin...</h3>";

$stmt_insert = $mysqli->prepare("INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro) VALUES (?, ?, ?, ?, 'admin', NOW())");
$stmt_insert->bind_param('ssss', $nombre_admin, $apellido_admin, $email_admin, $password_hash);

if ($stmt_insert->execute()) {
    $new_user_id = $mysqli->insert_id;
    echo "<p style='color: green; font-weight: bold;'>✓✓✓ Usuario creado exitosamente</p>";
    echo "<pre>";
    echo "ID Usuario: $new_user_id\n";
    echo "Nombre: $nombre_admin $apellido_admin\n";
    echo "Email: $email_admin\n";
    echo "Rol: admin\n";
    echo "</pre>";
} else {
    echo "<p style='color: red;'>✗ Error al crear usuario: " . htmlspecialchars($mysqli->error) . "</p>";
    exit;
}

// Paso 4: Verificar contraseña
echo "<h3>Paso 4: Verificando contraseña...</h3>";

$stmt_verify = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
$stmt_verify->bind_param('s', $email_admin);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();

if ($row = $result_verify->fetch_assoc()) {
    $verificacion = password_verify($password_admin, $row['contrasena']);
    
    if ($verificacion) {
        echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✓✓✓ Contraseña verificada correctamente</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗✗✗ Error: La contraseña NO se verifica correctamente</p>";
    }
    
    echo "<pre>";
    echo "ID: " . $row['id_usuario'] . "\n";
    echo "Nombre: " . htmlspecialchars($row['nombre'] . ' ' . $row['apellido']) . "\n";
    echo "Email: " . htmlspecialchars($row['email']) . "\n";
    echo "Rol: " . htmlspecialchars($row['rol']) . "\n";
    echo "</pre>";
}

// Paso 5: Limpiar sesión
echo "<h3>Paso 5: Limpiando sesión...</h3>";
session_start();
$_SESSION['login_attempts'] = [];
unset($_SESSION['login_attempts']);
echo "<p style='color: green;'>✓ Sesión limpiada</p>";

echo "<hr>";
echo "<h3>✓✓✓ Proceso Completado</h3>";
echo "<p><strong>Credenciales:</strong></p>";
echo "<ul>";
echo "<li><strong>Email:</strong> admin@test.com</li>";
echo "<li><strong>Contraseña:</strong> admin@test.com</li>";
echo "</ul>";
echo "<p><a href='login.php' style='font-size: 18px; font-weight: bold;'>→ Ir al Login</a></p>";
echo "<p><a href='login_directo_admin.php' style='font-size: 18px; font-weight: bold; color: green;'>→ Login Directo</a></p>";

$mysqli->close();
?>

