<?php
/**
 * ========================================================================
 * FIX COMPLETO DE LOGIN ADMIN
 * ========================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Limpiar TODA la sesión relacionada con login
$_SESSION['login_attempts'] = [];
unset($_SESSION['login_attempts']);

require_once 'config/database.php';

$email_admin = 'admin@test.com';
$password_admin = 'admin@test.com';

echo "<h2>Fix Completo de Login Admin</h2>";
echo "<hr>";

// 1. Verificar usuario y actualizar contraseña si es necesario
echo "<h3>1. Verificando y actualizando usuario...</h3>";

$stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin LIMIT 1");
$stmt->bind_param('s', $email_admin);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Crear usuario
    $password_hash = password_hash($password_admin, PASSWORD_BCRYPT);
    $nombre = 'Administrador';
    $apellido = 'Sistema';
    
    $stmt_insert = $mysqli->prepare("INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro) VALUES (?, ?, ?, ?, 'admin', NOW())");
    $stmt_insert->bind_param('ssss', $nombre, $apellido, $email_admin, $password_hash);
    
    if ($stmt_insert->execute()) {
        echo "<p style='color: green;'>✓ Usuario creado</p>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($mysqli->error) . "</p>";
    }
} else {
    $usuario = $result->fetch_assoc();
    
    // Verificar si la contraseña funciona
    $verificacion = password_verify($password_admin, $usuario['contrasena']);
    
    if (!$verificacion) {
        // Actualizar contraseña
        echo "<p style='color: orange;'>⚠ Contraseña no coincide, actualizando...</p>";
        $password_hash = password_hash($password_admin, PASSWORD_BCRYPT);
        
        $stmt_update = $mysqli->prepare("UPDATE Usuarios SET contrasena = ?, rol = 'admin' WHERE email = ?");
        $stmt_update->bind_param('ss', $password_hash, $email_admin);
        
        if ($stmt_update->execute()) {
            echo "<p style='color: green;'>✓ Contraseña actualizada</p>";
        } else {
            echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($mysqli->error) . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✓ Usuario y contraseña correctos</p>";
    }
    
    // Asegurar que el rol sea admin
    if (strtolower($usuario['rol']) !== 'admin') {
        $stmt_rol = $mysqli->prepare("UPDATE Usuarios SET rol = 'admin' WHERE email = ?");
        $stmt_rol->bind_param('s', $email_admin);
        $stmt_rol->execute();
        echo "<p style='color: green;'>✓ Rol actualizado a admin</p>";
    }
}

// 2. Limpiar sesión completamente
echo "<h3>2. Limpiando sesión...</h3>";
session_destroy();
session_start();
$_SESSION['login_attempts'] = [];
echo "<p style='color: green;'>✓ Sesión limpiada</p>";

// 3. Test de login directo
echo "<h3>3. Test de login directo...</h3>";

$stmt_test = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin LIMIT 1");
$stmt_test->bind_param('s', $email_admin);
$stmt_test->execute();
$result_test = $stmt_test->get_result();

if ($row = $result_test->fetch_assoc()) {
    // Simular exactamente lo que hace login.php
    $email = trim($email_admin);
    $password = $password_admin;
    
    if (password_verify($password, $row['contrasena'])) {
        // Crear sesión como lo hace login.php
        $_SESSION['id_usuario'] = $row['id_usuario'];
        $_SESSION['nombre'] = $row['nombre'];
        $_SESSION['apellido'] = $row['apellido'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['rol'] = strtolower($row['rol']);
        
        echo "<p style='color: green; font-weight: bold; font-size: 20px;'>✓✓✓ LOGIN EXITOSO</p>";
        echo "<pre>";
        echo "ID Usuario: " . $_SESSION['id_usuario'] . "\n";
        echo "Nombre: " . htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) . "\n";
        echo "Email: " . htmlspecialchars($_SESSION['email']) . "\n";
        echo "Rol: " . htmlspecialchars($_SESSION['rol']) . "\n";
        echo "</pre>";
        
        echo "<hr>";
        echo "<h3>✓ Sesión creada correctamente</h3>";
        echo "<p><a href='admin.php' style='font-size: 18px; font-weight: bold; color: green;'>→ Ir al Panel Admin</a></p>";
        
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ password_verify() falló</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Usuario no encontrado</p>";
}

// 4. Información de debug
echo "<hr>";
echo "<h3>4. Información de Debug:</h3>";
echo "<pre>";
echo "Email usado: " . htmlspecialchars($email_admin) . "\n";
echo "Password usado: " . htmlspecialchars($password_admin) . "\n";
echo "Email length: " . strlen($email_admin) . "\n";
echo "Password length: " . strlen($password_admin) . "\n";
echo "Email bytes: " . bin2hex($email_admin) . "\n";
echo "Password bytes: " . bin2hex($password_admin) . "\n";
echo "</pre>";

echo "<hr>";
echo "<p><strong>Credenciales para login:</strong></p>";
echo "<ul>";
echo "<li><strong>Email:</strong> admin@test.com</li>";
echo "<li><strong>Contraseña:</strong> admin@test.com</li>";
echo "</ul>";
echo "<p><a href='login.php'>Ir al Login</a></p>";
echo "<p><strong>IMPORTANTE:</strong> Si el login aún falla, elimina este archivo y verifica que no haya espacios ocultos al copiar/pegar.</p>";

$mysqli->close();
?>

