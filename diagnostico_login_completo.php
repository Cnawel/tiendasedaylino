<?php
/**
 * ========================================================================
 * DIAGNÓSTICO COMPLETO DE LOGIN - Comparación de usuarios
 * ========================================================================
 * Verifica por qué admin@test.com no funciona pero admin2@test.com sí
 * ========================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once 'config/database.php';

$usuarios_test = [
    'admin@test.com' => 'admin@test.com',
    'admin2@test.com' => 'admin2@test.com'
];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnóstico Login</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
    h2 { color: #555; margin-top: 30px; border-left: 4px solid #007bff; padding-left: 10px; }
    h3 { color: #666; margin-top: 20px; }
    .usuario { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
    .ok { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; border: 1px solid #ddd; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
    th { background: #007bff; color: white; }
    tr:nth-child(even) { background: #f8f9fa; }
    .comparacion { display: flex; gap: 20px; margin: 20px 0; }
    .comparacion > div { flex: 1; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔍 Diagnóstico Completo de Login</h1>";
echo "<p><strong>Objetivo:</strong> Comparar admin@test.com (no funciona) vs admin2@test.com (funciona)</p>";
echo "<hr>";

// ========================================================================
// 1. VERIFICAR EXISTENCIA DE USUARIOS EN BD
// ========================================================================
echo "<h2>1. Verificación de Existencia en Base de Datos</h2>";

$usuarios_bd = [];
foreach ($usuarios_test as $email => $password) {
    $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $usuarios_bd[$email] = $row;
        echo "<div class='usuario'>";
        echo "<h3>✅ Usuario encontrado: " . htmlspecialchars($email) . "</h3>";
        echo "<pre>";
        echo "ID: " . $row['id_usuario'] . "\n";
        echo "Nombre: " . htmlspecialchars($row['nombre'] . ' ' . $row['apellido']) . "\n";
        echo "Email: " . htmlspecialchars($row['email']) . "\n";
        echo "Rol: " . htmlspecialchars($row['rol']) . "\n";
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div class='usuario'>";
        echo "<h3 class='error'>❌ Usuario NO encontrado: " . htmlspecialchars($email) . "</h3>";
        echo "</div>";
    }
    $stmt->close();
}

if (count($usuarios_bd) !== 2) {
    echo "<p class='error'>⚠️ Ambos usuarios deben existir en la BD para continuar el diagnóstico.</p>";
    echo "</div></body></html>";
    exit;
}

// ========================================================================
// 2. ANÁLISIS DE EMAILS - Byte por byte
// ========================================================================
echo "<h2>2. Análisis Detallado de Emails</h2>";

echo "<table>";
echo "<tr><th>Usuario</th><th>Email Original</th><th>Longitud</th><th>Bytes (hex)</th><th>Trim()</th><th>Bytes después Trim</th></tr>";

foreach ($usuarios_bd as $email_input => $usuario) {
    $email_bd = $usuario['email'];
    $email_trim = trim($email_bd);
    
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($email_input) . "</strong></td>";
    echo "<td>" . htmlspecialchars($email_bd) . "</td>";
    echo "<td>" . strlen($email_bd) . " bytes</td>";
    echo "<td><code>" . bin2hex($email_bd) . "</code></td>";
    echo "<td>" . htmlspecialchars($email_trim) . "</td>";
    echo "<td><code>" . bin2hex($email_trim) . "</code></td>";
    echo "</tr>";
}

echo "</table>";

// Comparar si los emails son idénticos byte por byte
$email1 = $usuarios_bd['admin@test.com']['email'];
$email2 = $usuarios_bd['admin2@test.com']['email'];

if ($email1 === $email2) {
    echo "<p class='error'>⚠️ Los emails en BD son idénticos (no debería pasar si son usuarios diferentes)</p>";
} else {
    echo "<p class='ok'>✓ Los emails en BD son diferentes (correcto)</p>";
}

// ========================================================================
// 3. VERIFICACIÓN DE CONTRASEÑAS
// ========================================================================
echo "<h2>3. Verificación de Contraseñas</h2>";

echo "<table>";
echo "<tr><th>Usuario</th><th>Password a verificar</th><th>Hash en BD</th><th>password_verify()</th><th>Hash length</th></tr>";

foreach ($usuarios_bd as $email_input => $usuario) {
    $password_test = $usuarios_test[$email_input];
    $hash_bd = $usuario['contrasena'];
    $verificacion = password_verify($password_test, $hash_bd);
    
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($email_input) . "</strong></td>";
    echo "<td>" . htmlspecialchars($password_test) . "</td>";
    echo "<td><code>" . substr($hash_bd, 0, 30) . "...</code></td>";
    echo "<td>" . ($verificacion ? "<span class='ok'>✅ TRUE</span>" : "<span class='error'>❌ FALSE</span>") . "</td>";
    echo "<td>" . strlen($hash_bd) . " bytes</td>";
    echo "</tr>";
}

echo "</table>";

// ========================================================================
// 4. PRUEBA DE CONSULTAS EXACTAS DE login.php
// ========================================================================
echo "<h2>4. Prueba de Consultas Exactas (como en login.php)</h2>";

foreach ($usuarios_test as $email_input => $password_input) {
    echo "<div class='usuario'>";
    echo "<h3>Prueba para: " . htmlspecialchars($email_input) . "</h3>";
    
    // Simular exactamente lo que hace login.php
    $email = trim($email_input);
    $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
    $password = $password_input;
    
    echo "<p><strong>Email procesado:</strong> " . htmlspecialchars($email) . " (bytes: " . bin2hex($email) . ")</p>";
    
    // Consulta 1: Normal
    $stmt1 = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
    $stmt1->bind_param('s', $email);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $found1 = $result1->num_rows > 0;
    $row1 = $found1 ? $result1->fetch_assoc() : null;
    $stmt1->close();
    
    echo "<p><strong>Consulta 1 (normal):</strong> " . ($found1 ? "<span class='ok'>✅ Encontrado</span>" : "<span class='error'>❌ No encontrado</span>") . "</p>";
    
    // Consulta 2: Con TRIM
    $stmt2 = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE TRIM(email) = ? LIMIT 1");
    $stmt2->bind_param('s', $email);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $found2 = $result2->num_rows > 0;
    $row2 = $found2 ? $result2->fetch_assoc() : null;
    $stmt2->close();
    
    echo "<p><strong>Consulta 2 (TRIM):</strong> " . ($found2 ? "<span class='ok'>✅ Encontrado</span>" : "<span class='error'>❌ No encontrado</span>") . "</p>";
    
    // Consulta 3: Con COLLATE utf8mb4_bin
    try {
        $stmt3 = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin LIMIT 1");
        $stmt3->bind_param('s', $email);
        $stmt3->execute();
        $result3 = $stmt3->get_result();
        $found3 = $result3->num_rows > 0;
        $row3 = $found3 ? $result3->fetch_assoc() : null;
        $stmt3->close();
        
        echo "<p><strong>Consulta 3 (COLLATE utf8mb4_bin):</strong> " . ($found3 ? "<span class='ok'>✅ Encontrado</span>" : "<span class='error'>❌ No encontrado</span>") . "</p>";
    } catch (Exception $e) {
        echo "<p><strong>Consulta 3 (COLLATE utf8mb4_bin):</strong> <span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span></p>";
    }
    
    // Verificar contraseña si se encontró usuario
    if ($found1 && $row1) {
        $password_ok = password_verify($password, $row1['contrasena']);
        echo "<p><strong>password_verify():</strong> " . ($password_ok ? "<span class='ok'>✅ TRUE</span>" : "<span class='error'>❌ FALSE</span>") . "</p>";
        
        if ($password_ok) {
            echo "<p class='ok'>✅ LOGIN SIMULADO EXITOSO</p>";
        } else {
            echo "<p class='error'>❌ LOGIN SIMULADO FALLIDO - Contraseña incorrecta</p>";
        }
    } else {
        echo "<p class='error'>❌ LOGIN SIMULADO FALLIDO - Usuario no encontrado</p>";
    }
    
    echo "</div>";
}

// ========================================================================
// 5. COMPARACIÓN DIRECTA DE EMAILS EN BD
// ========================================================================
echo "<h2>5. Comparación Directa de Emails en Base de Datos</h2>";

$email_bd_1 = $usuarios_bd['admin@test.com']['email'];
$email_bd_2 = $usuarios_bd['admin2@test.com']['email'];

echo "<div class='comparacion'>";
echo "<div><h3>admin@test.com en BD</h3><pre>" . htmlspecialchars($email_bd_1) . "\nBytes: " . bin2hex($email_bd_1) . "\nLongitud: " . strlen($email_bd_1) . "</pre></div>";
echo "<div><h3>admin2@test.com en BD</h3><pre>" . htmlspecialchars($email_bd_2) . "\nBytes: " . bin2hex($email_bd_2) . "\nLongitud: " . strlen($email_bd_2) . "</pre></div>";
echo "</div>";

// Buscar con ambos emails exactamente como están en BD
echo "<h3>Búsqueda con email exacto de BD</h3>";

foreach ($usuarios_bd as $email_input => $usuario) {
    $email_bd_exacto = $usuario['email'];
    
    $stmt = $mysqli->prepare("SELECT id_usuario, email FROM Usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email_bd_exacto);
    $stmt->execute();
    $result = $stmt->get_result();
    $found = $result->num_rows > 0;
    
    echo "<p><strong>" . htmlspecialchars($email_input) . "</strong> usando email exacto de BD: " . ($found ? "<span class='ok'>✅ Encontrado</span>" : "<span class='error'>❌ No encontrado</span>") . "</p>";
    
    $stmt->close();
}

// ========================================================================
// 6. VERIFICAR COLACIÓN DE CARACTERES
// ========================================================================
echo "<h2>6. Verificación de Colación y Encoding</h2>";

$result = $mysqli->query("SHOW FULL COLUMNS FROM Usuarios WHERE Field = 'email'");
if ($row = $result->fetch_assoc()) {
    echo "<pre>";
    echo "Campo: " . htmlspecialchars($row['Field']) . "\n";
    echo "Tipo: " . htmlspecialchars($row['Type']) . "\n";
    echo "Colación: " . htmlspecialchars($row['Collation'] ?? 'N/A') . "\n";
    echo "</pre>";
}

// Verificar charset de conexión
echo "<h3>Charset de Conexión</h3>";
echo "<pre>";
echo "Charset MySQLi: " . $mysqli->character_set_name() . "\n";
$result = $mysqli->query("SELECT @@character_set_client, @@character_set_connection, @@character_set_database, @@character_set_results");
if ($row = $result->fetch_row()) {
    echo "character_set_client: " . $row[0] . "\n";
    echo "character_set_connection: " . $row[1] . "\n";
    echo "character_set_database: " . $row[2] . "\n";
    echo "character_set_results: " . $row[3] . "\n";
}
echo "</pre>";

// ========================================================================
// 7. PRUEBA DE LOGIN REAL SIMULADO
// ========================================================================
echo "<h2>7. Simulación de Login Real (POST)</h2>";

foreach ($usuarios_test as $email_input => $password_input) {
    echo "<div class='usuario'>";
    echo "<h3>Simulación POST para: " . htmlspecialchars($email_input) . "</h3>";
    
    // Simular $_POST
    $_POST['email'] = $email_input;
    $_POST['password'] = $password_input;
    
    // Procesar como login.php
    $email_raw = $_POST['email'] ?? '';
    $email = trim($email_raw);
    $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
    $password_raw = $_POST['password'] ?? '';
    $password = $password_raw;
    
    echo "<p><strong>Email raw:</strong> " . htmlspecialchars($email_raw) . " (bytes: " . bin2hex($email_raw) . ")</p>";
    echo "<p><strong>Email procesado:</strong> " . htmlspecialchars($email) . " (bytes: " . bin2hex($email) . ")</p>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . " (bytes: " . bin2hex($password) . ")</p>";
    
    // Consulta exacta de login.php
    $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Intentar con TRIM
        $stmt->close();
        $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE TRIM(email) = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    if ($result && $result->num_rows === 0) {
        // Intentar con COLLATE
        $stmt->close();
        try {
            $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
        } catch (Exception $e) {
            // Ignorar error
        }
    }
    
    if ($result && ($row = $result->fetch_assoc())) {
        echo "<p class='ok'>✅ Usuario encontrado en BD</p>";
        
        if (password_verify($password, $row['contrasena'])) {
            echo "<p class='ok'>✅✅✅ LOGIN EXITOSO - Contraseña correcta</p>";
        } else {
            echo "<p class='error'>❌ LOGIN FALLIDO - Contraseña incorrecta</p>";
            echo "<p><strong>Hash en BD:</strong> " . substr($row['contrasena'], 0, 30) . "...</p>";
        }
    } else {
        echo "<p class='error'>❌ LOGIN FALLIDO - Usuario no encontrado</p>";
    }
    
    $stmt->close();
    echo "</div>";
}

// ========================================================================
// 8. RESUMEN Y CONCLUSIÓN
// ========================================================================
echo "<h2>8. Resumen y Diagnóstico</h2>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;'>";
echo "<h3>Resumen de Hallazgos:</h3>";
echo "<ul>";
echo "<li>Verifica si ambos usuarios existen en la BD</li>";
echo "<li>Compara los emails byte por byte para detectar caracteres invisibles</li>";
echo "<li>Verifica que las contraseñas funcionen con password_verify()</li>";
echo "<li>Comprueba que las consultas SQL encuentren ambos usuarios</li>";
echo "<li>Revisa la colación de caracteres de la tabla</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><a href='login.php'>← Volver al Login</a></p>";

echo "</div></body></html>";

$mysqli->close();
?>

