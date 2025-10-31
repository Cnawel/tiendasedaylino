<?php
/**
 * ========================================================================
 * DEBUG DE LOGIN - Diagnóstico detallado
 * ========================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

$email_admin = 'admin@test.com';
$password_admin = 'admin@test.com';

echo "<h2>Debug Detallado de Login</h2>";
echo "<hr>";

// Simular exactamente lo que hace login.php
echo "<h3>Simulación exacta de login.php:</h3>";

// 1. Simular $_POST
$_POST['email'] = $email_admin;
$_POST['password'] = $password_admin;

echo "<p><strong>Datos simulados:</strong></p>";
echo "<pre>";
echo "Email POST: " . htmlspecialchars($_POST['email']) . "\n";
echo "Password POST: " . htmlspecialchars($_POST['password']) . "\n";
echo "Email length: " . strlen($_POST['email']) . "\n";
echo "Password length: " . strlen($_POST['password']) . "\n";
echo "</pre>";

// 2. Procesar como login.php
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

echo "<p><strong>Después de procesar:</strong></p>";
echo "<pre>";
echo "Email (trimmed): " . htmlspecialchars($email) . "\n";
echo "Password (sin trim): " . htmlspecialchars($password) . "\n";
echo "Email length: " . strlen($email) . "\n";
echo "Password length: " . strlen($password) . "\n";
echo "</pre>";

// 3. Consulta BD
$stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "<p style='color: green;'>✓ Usuario encontrado</p>";
    
    // 4. Verificar contraseña
    echo "<h3>Verificación de contraseña:</h3>";
    echo "<pre>";
    echo "Password recibido: [" . htmlspecialchars($password) . "]\n";
    echo "Password length: " . strlen($password) . "\n";
    echo "Password bytes: " . bin2hex($password) . "\n";
    echo "Hash en BD: " . substr($row['contrasena'], 0, 30) . "...\n";
    echo "</pre>";
    
    $verificacion = password_verify($password, $row['contrasena']);
    
    echo "<p><strong>Resultado password_verify():</strong> ";
    if ($verificacion) {
        echo "<span style='color: green; font-weight: bold; font-size: 20px;'>TRUE ✓</span>";
    } else {
        echo "<span style='color: red; font-weight: bold; font-size: 20px;'>FALSE ✗</span>";
    }
    echo "</p>";
    
    // Test con diferentes variaciones
    echo "<h3>Tests con variaciones:</h3>";
    
    $variaciones = [
        'admin@test.com',
        trim('admin@test.com'),
        rtrim('admin@test.com'),
        ltrim('admin@test.com'),
        'admin@test.com ',
        ' admin@test.com',
        ' admin@test.com ',
    ];
    
    foreach ($variaciones as $variacion) {
        $test = password_verify($variacion, $row['contrasena']);
        $status = $test ? '✓' : '✗';
        $color = $test ? 'green' : 'red';
        echo "<p style='color: $color;'>$status Test con: [" . htmlspecialchars($variacion) . "] (length: " . strlen($variacion) . ") = " . ($test ? 'TRUE' : 'FALSE') . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ Usuario NO encontrado</p>";
}

// Limpiar intentos fallidos
if (isset($_SESSION['login_attempts'])) {
    unset($_SESSION['login_attempts']);
    echo "<p style='color: green;'>✓ Intentos fallidos limpiados de la sesión</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>Ir al Login</a></p>";
echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo después de revisar.</p>";

$mysqli->close();
?>

