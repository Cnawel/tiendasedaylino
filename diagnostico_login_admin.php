<?php
/**
 * ========================================================================
 * DIAGNÓSTICO COMPLETO DE LOGIN ADMIN
 * ========================================================================
 * Este script diagnostica paso a paso por qué falla el login de admin@test.com
 * ========================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Limpiar bloqueos
$_SESSION['login_attempts'] = [];

require_once 'config/database.php';

$email_admin = 'admin@test.com';
$password_admin = 'admin@test.com';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnóstico Login Admin</title>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .warning{color:orange;font-weight:bold;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>";
echo "</head><body>";
echo "<h1>🔍 Diagnóstico Completo de Login Admin</h1>";
echo "<hr>";

// PASO 1: Verificar que el usuario existe en BD
echo "<h2>PASO 1: Verificar existencia del usuario</h2>";

$queries = [
    "Sin COLLATE" => "SELECT id_usuario, nombre, apellido, email, contrasena, rol, LENGTH(email) as len_email, HEX(email) as hex_email FROM Usuarios WHERE email = ? LIMIT 1",
    "Con COLLATE utf8mb4_bin" => "SELECT id_usuario, nombre, apellido, email, contrasena, rol, LENGTH(email) as len_email, HEX(email) as hex_email FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin LIMIT 1",
    "Con TRIM" => "SELECT id_usuario, nombre, apellido, email, contrasena, rol, LENGTH(email) as len_email, HEX(email) as hex_email FROM Usuarios WHERE TRIM(email) = ? LIMIT 1",
    "Con LIKE" => "SELECT id_usuario, nombre, apellido, email, contrasena, rol, LENGTH(email) as len_email, HEX(email) as hex_email FROM Usuarios WHERE email LIKE ? LIMIT 1"
];

$usuario_encontrado = null;
$metodo_exitoso = null;

foreach ($queries as $metodo => $query) {
    $stmt = $mysqli->prepare($query);
    if ($metodo === "Con LIKE") {
        $email_like = '%' . $email_admin . '%';
        $stmt->bind_param('s', $email_like);
    } else {
        $stmt->bind_param('s', $email_admin);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "<p class='ok'>✓ {$metodo}: Usuario encontrado</p>";
        echo "<pre>";
        echo "ID: " . $row['id_usuario'] . "\n";
        echo "Nombre: " . htmlspecialchars($row['nombre'] . ' ' . $row['apellido']) . "\n";
        echo "Email: [" . htmlspecialchars($row['email']) . "]\n";
        echo "Email length: " . $row['len_email'] . " bytes\n";
        echo "Email hex: " . $row['hex_email'] . "\n";
        echo "Rol: " . htmlspecialchars($row['rol']) . "\n";
        echo "Hash (primeros 30 chars): " . substr($row['contrasena'], 0, 30) . "...\n";
        echo "</pre>";
        
        if ($usuario_encontrado === null) {
            $usuario_encontrado = $row;
            $metodo_exitoso = $metodo;
        }
    } else {
        echo "<p class='error'>✗ {$metodo}: Usuario NO encontrado</p>";
    }
    $stmt->close();
}

if ($usuario_encontrado === null) {
    echo "<p class='error'><strong>ERROR CRÍTICO: El usuario admin@test.com NO existe en la base de datos</strong></p>";
    echo "<p><a href='fix_admin_login.php'>Ejecutar Fix Admin Login</a></p>";
    echo "</body></html>";
    exit;
}

echo "<hr>";

// PASO 2: Verificar email exacto
echo "<h2>PASO 2: Comparación exacta del email</h2>";
$email_bd = $usuario_encontrado['email'];
$email_esperado = $email_admin;

echo "<pre>";
echo "Email esperado: [" . htmlspecialchars($email_esperado) . "] (length: " . strlen($email_esperado) . ")\n";
echo "Email en BD:    [" . htmlspecialchars($email_bd) . "] (length: " . strlen($email_bd) . ")\n";
echo "¿Son iguales?: " . ($email_bd === $email_esperado ? "SÍ ✓" : "NO ✗") . "\n";
echo "</pre>";

if ($email_bd !== $email_esperado) {
    echo "<p class='warning'>⚠ El email en BD es diferente. Limpiando...</p>";
    
    // Limpiar email en BD
    $stmt_clean = $mysqli->prepare("UPDATE Usuarios SET email = ? WHERE id_usuario = ?");
    $stmt_clean->bind_param('si', $email_admin, $usuario_encontrado['id_usuario']);
    if ($stmt_clean->execute()) {
        echo "<p class='ok'>✓ Email limpiado en BD</p>";
        // Recargar datos
        $stmt_reload = $mysqli->prepare("SELECT * FROM Usuarios WHERE id_usuario = ? LIMIT 1");
        $stmt_reload->bind_param('i', $usuario_encontrado['id_usuario']);
        $stmt_reload->execute();
        $result_reload = $stmt_reload->get_result();
        $usuario_encontrado = $result_reload->fetch_assoc();
        $stmt_reload->close();
    } else {
        echo "<p class='error'>✗ Error al limpiar: " . htmlspecialchars($mysqli->error) . "</p>";
    }
    $stmt_clean->close();
}

echo "<hr>";

// PASO 3: Verificar contraseña
echo "<h2>PASO 3: Verificación de contraseña</h2>";

$hash_bd = $usuario_encontrado['contrasena'];
$password_a_verificar = $password_admin;

echo "<pre>";
echo "Password a verificar: [" . htmlspecialchars($password_a_verificar) . "] (length: " . strlen($password_a_verificar) . ")\n";
echo "Hash en BD: " . substr($hash_bd, 0, 30) . "...\n";
echo "</pre>";

$verificacion = password_verify($password_a_verificar, $hash_bd);

if ($verificacion) {
    echo "<p class='ok'>✓ password_verify() = TRUE</p>";
} else {
    echo "<p class='error'>✗ password_verify() = FALSE</p>";
    echo "<p class='warning'>⚠ Regenerando contraseña...</p>";
    
    $nuevo_hash = password_hash($password_admin, PASSWORD_BCRYPT);
    $stmt_pass = $mysqli->prepare("UPDATE Usuarios SET contrasena = ? WHERE id_usuario = ?");
    $stmt_pass->bind_param('si', $nuevo_hash, $usuario_encontrado['id_usuario']);
    
    if ($stmt_pass->execute()) {
        echo "<p class='ok'>✓ Contraseña regenerada</p>";
        // Verificar de nuevo
        $verificacion_nueva = password_verify($password_a_verificar, $nuevo_hash);
        if ($verificacion_nueva) {
            echo "<p class='ok'>✓ Verificación exitosa con nuevo hash</p>";
            $hash_bd = $nuevo_hash;
        }
    } else {
        echo "<p class='error'>✗ Error al regenerar: " . htmlspecialchars($mysqli->error) . "</p>";
    }
    $stmt_pass->close();
}

echo "<hr>";

// PASO 4: Verificar rol
echo "<h2>PASO 4: Verificación de rol</h2>";
$rol_bd = strtolower(trim($usuario_encontrado['rol'] ?? ''));

echo "<pre>";
echo "Rol en BD: [" . htmlspecialchars($usuario_encontrado['rol']) . "]\n";
echo "Rol normalizado: [" . htmlspecialchars($rol_bd) . "]\n";
echo "¿Es admin?: " . ($rol_bd === 'admin' ? "SÍ ✓" : "NO ✗") . "\n";
echo "</pre>";

if ($rol_bd !== 'admin') {
    echo "<p class='warning'>⚠ Actualizando rol a admin...</p>";
    $stmt_rol = $mysqli->prepare("UPDATE Usuarios SET rol = 'admin' WHERE id_usuario = ?");
    $stmt_rol->bind_param('i', $usuario_encontrado['id_usuario']);
    if ($stmt_rol->execute()) {
        echo "<p class='ok'>✓ Rol actualizado a admin</p>";
        $rol_bd = 'admin';
    } else {
        echo "<p class='error'>✗ Error: " . htmlspecialchars($mysqli->error) . "</p>";
    }
    $stmt_rol->close();
}

echo "<hr>";

// PASO 5: Simular login exacto como login.php
echo "<h2>PASO 5: Simulación exacta del proceso de login.php</h2>";

// Limpiar sesión
session_destroy();
session_start();

// Simular POST
$_POST['email'] = $email_admin;
$_POST['password'] = $password_admin;

// Procesar como login.php
$email_raw = $_POST['email'] ?? '';
$email = trim($email_raw);
$email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
$password_raw = $_POST['password'] ?? '';
$password = $password_raw;

echo "<pre>";
echo "Email después de procesar: [" . htmlspecialchars($email) . "] (length: " . strlen($email) . ")\n";
echo "Password después de procesar: [" . htmlspecialchars($password) . "] (length: " . strlen($password) . ")\n";
echo "</pre>";

// Buscar usuario (igual que login.php)
$stmt_login = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin LIMIT 1");
$stmt_login->bind_param('s', $email);
$stmt_login->execute();
$result_login = $stmt_login->get_result();

if ($result_login->num_rows === 0) {
    echo "<p class='warning'>⚠ No encontrado con COLLATE utf8mb4_bin, intentando con TRIM...</p>";
    $stmt_login->close();
    $stmt_login = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE TRIM(email) = ? LIMIT 1");
    $stmt_login->bind_param('s', $email);
    $stmt_login->execute();
    $result_login = $stmt_login->get_result();
}

if ($row_login = $result_login->fetch_assoc()) {
    echo "<p class='ok'>✓ Usuario encontrado para login</p>";
    
    if (password_verify($password, $row_login['contrasena'])) {
        echo "<p class='ok'>✓ Contraseña verificada correctamente</p>";
        
        // Crear sesión como login.php
        $rol_bd_login = strtolower(trim($row_login['rol'] ?? ''));
        $roles_validos = ['admin', 'ventas', 'marketing', 'cliente'];
        
        if (!in_array($rol_bd_login, $roles_validos, true)) {
            $rol_bd_login = 'cliente';
        }
        
        $_SESSION['id_usuario'] = $row_login['id_usuario'];
        $_SESSION['nombre'] = $row_login['nombre'];
        $_SESSION['apellido'] = $row_login['apellido'];
        $_SESSION['email'] = $row_login['email'];
        $_SESSION['rol'] = $rol_bd_login;
        
        echo "<p class='ok'>✓ Sesión creada</p>";
        echo "<pre>";
        echo "ID Usuario: " . $_SESSION['id_usuario'] . "\n";
        echo "Nombre: " . htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) . "\n";
        echo "Email: " . htmlspecialchars($_SESSION['email']) . "\n";
        echo "Rol: " . htmlspecialchars($_SESSION['rol']) . "\n";
        echo "</pre>";
        
        // Verificar funciones de auth
        require_once 'includes/auth_check.php';
        
        echo "<h3>Verificación de funciones de autenticación:</h3>";
        echo "<pre>";
        echo "isLoggedIn(): " . (isLoggedIn() ? "TRUE ✓" : "FALSE ✗") . "\n";
        echo "isAdmin(): " . (isAdmin() ? "TRUE ✓" : "FALSE ✗") . "\n";
        echo "</pre>";
        
        if (isAdmin()) {
            echo "<p class='ok'><strong>✓✓✓ LOGIN COMPLETAMENTE EXITOSO ✓✓✓</strong></p>";
            echo "<p><a href='admin.php' style='font-size: 20px; font-weight: bold; color: green;'>→ Ir al Panel Admin</a></p>";
        } else {
            echo "<p class='error'><strong>✗ ERROR: El usuario está logueado pero isAdmin() retorna FALSE</strong></p>";
            echo "<pre>";
            echo "Rol en sesión: " . htmlspecialchars($_SESSION['rol'] ?? 'NO DEFINIDO') . "\n";
            echo "strtolower(rol): " . htmlspecialchars(strtolower($_SESSION['rol'] ?? '')) . "\n";
            echo "</pre>";
        }
        
    } else {
        echo "<p class='error'>✗ password_verify() falló durante simulación</p>";
    }
} else {
    echo "<p class='error'>✗ Usuario NO encontrado durante simulación de login</p>";
}

$stmt_login->close();

echo "<hr>";
echo "<h2>Resumen Final</h2>";
echo "<ul>";
echo "<li><strong>Email:</strong> admin@test.com</li>";
echo "<li><strong>Contraseña:</strong> admin@test.com</li>";
echo "<li><strong>Usuario existe:</strong> " . ($usuario_encontrado !== null ? "SÍ ✓" : "NO ✗") . "</li>";
echo "<li><strong>Email coincide:</strong> " . ($usuario_encontrado && $usuario_encontrado['email'] === $email_admin ? "SÍ ✓" : "NO ✗") . "</li>";
echo "<li><strong>Contraseña válida:</strong> " . ($verificacion ? "SÍ ✓" : "NO ✗") . "</li>";
echo "<li><strong>Rol correcto:</strong> " . ($rol_bd === 'admin' ? "SÍ ✓" : "NO ✗") . "</li>";
echo "</ul>";

echo "<p><a href='login.php'>→ Probar Login Real</a></p>";
echo "<p><a href='limpiar-sesion-login.php'>→ Limpiar Sesión de Login</a></p>";

$mysqli->close();
echo "</body></html>";
?>

