<?php
/**
 * ========================================================================
 * CORREGIR COLACIÓN DEL CAMPO EMAIL
 * ========================================================================
 * Cambia la colación del campo email de utf8mb4_0900_ai_ci a utf8mb4_unicode_ci
 * para mantener consistencia con el código SQL y las consultas
 * ========================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Corregir Colación Email</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
    h2 { color: #555; margin-top: 30px; border-left: 4px solid #007bff; padding-left: 10px; }
    .ok { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; border: 1px solid #ddd; }
    .info-box { background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #2196F3; margin: 15px 0; }
    .success-box { background: #e8f5e9; padding: 15px; border-radius: 5px; border-left: 4px solid #4CAF50; margin: 15px 0; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔧 Corregir Colación del Campo Email</h1>";
echo "<hr>";

// ========================================================================
// 1. VERIFICAR COLACIÓN ACTUAL
// ========================================================================
echo "<h2>1. Verificación de Colación Actual</h2>";

$result = $mysqli->query("SHOW FULL COLUMNS FROM Usuarios WHERE Field = 'email'");
if ($row = $result->fetch_assoc()) {
    $colacion_actual = $row['Collation'] ?? 'N/A';
    $tipo_actual = $row['Type'] ?? 'N/A';
    
    echo "<div class='info-box'>";
    echo "<h3>Estado Actual:</h3>";
    echo "<pre>";
    echo "Campo: " . htmlspecialchars($row['Field']) . "\n";
    echo "Tipo: " . htmlspecialchars($tipo_actual) . "\n";
    echo "Colación Actual: <strong>" . htmlspecialchars($colacion_actual) . "</strong>\n";
    echo "</pre>";
    echo "</div>";
    
    // Verificar si necesita corrección
    $colacion_esperada = 'utf8mb4_unicode_ci';
    $necesita_correccion = ($colacion_actual !== $colacion_esperada);
    
    if ($necesita_correccion) {
        echo "<div class='warning'>";
        echo "<p><strong>⚠️ Problema detectado:</strong> La colación actual es <code>" . htmlspecialchars($colacion_actual) . "</code></p>";
        echo "<p>La colación esperada según el código SQL es <code>" . htmlspecialchars($colacion_esperada) . "</code></p>";
        echo "<p>Esta diferencia puede causar problemas en la comparación de emails, especialmente con caracteres especiales.</p>";
        echo "</div>";
    } else {
        echo "<div class='success-box'>";
        echo "<p class='ok'>✅ La colación ya está correcta: <code>" . htmlspecialchars($colacion_actual) . "</code></p>";
        echo "</div>";
    }
} else {
    echo "<p class='error'>❌ No se pudo obtener información del campo email</p>";
    echo "</div></body></html>";
    exit;
}

// ========================================================================
// 2. VERIFICAR COLACIÓN DE LA TABLA Y BASE DE DATOS
// ========================================================================
echo "<h2>2. Verificación de Colación de Tabla y Base de Datos</h2>";

// Colación de la tabla
$result_table = $mysqli->query("SHOW TABLE STATUS WHERE Name = 'Usuarios'");
if ($row_table = $result_table->fetch_assoc()) {
    echo "<pre>";
    echo "Tabla Usuarios:\n";
    echo "  Collation: " . htmlspecialchars($row_table['Collation'] ?? 'N/A') . "\n";
    echo "</pre>";
}

// Colación de la base de datos
$result_db = $mysqli->query("SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'tiendasedaylino_db'");
if ($row_db = $result_db->fetch_assoc()) {
    echo "<pre>";
    echo "Base de Datos:\n";
    echo "  Default Collation: " . htmlspecialchars($row_db['DEFAULT_COLLATION_NAME'] ?? 'N/A') . "\n";
    echo "</pre>";
}

// ========================================================================
// 3. VERIFICAR USUARIOS ANTES DE CAMBIAR COLACIÓN
// ========================================================================
echo "<h2>3. Verificación de Usuarios Antes del Cambio</h2>";

$usuarios_test = ['admin@test.com', 'admin2@test.com'];
$usuarios_encontrados = [];

foreach ($usuarios_test as $email) {
    $stmt = $mysqli->prepare("SELECT id_usuario, email FROM Usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $usuarios_encontrados[$email] = $row;
        echo "<p class='ok'>✅ Usuario encontrado: " . htmlspecialchars($email) . " (ID: " . $row['id_usuario'] . ")</p>";
    } else {
        echo "<p class='error'>❌ Usuario NO encontrado: " . htmlspecialchars($email) . "</p>";
    }
    $stmt->close();
}

if (count($usuarios_encontrados) !== 2) {
    echo "<p class='warning'>⚠️ No se encontraron ambos usuarios. El cambio de colación puede continuar pero verifica los usuarios después.</p>";
}

// ========================================================================
// 4. REALIZAR CAMBIO DE COLACIÓN
// ========================================================================
echo "<h2>4. Aplicar Corrección de Colación</h2>";

if ($necesita_correccion) {
    echo "<div class='info-box'>";
    echo "<p><strong>⚠️ IMPORTANTE:</strong> Se va a modificar la estructura de la tabla.</p>";
    echo "<p>Esta operación es segura y no afecta los datos, solo cambia cómo se comparan los strings.</p>";
    echo "</div>";
    
    // Realizar el cambio de colación
    // Nota: Para cambiar la colación de una columna con índice UNIQUE, puede ser necesario
    // eliminar y recrear el índice. MySQL 8.0+ maneja esto automáticamente.
    
    $sql = "ALTER TABLE Usuarios MODIFY COLUMN email VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL";
    
    echo "<p><strong>Ejecutando:</strong> <code>" . htmlspecialchars($sql) . "</code></p>";
    
    if ($mysqli->query($sql)) {
        echo "<p class='ok'>✅ Colación cambiada exitosamente</p>";
        
        // Verificar el cambio
        $result_verify = $mysqli->query("SHOW FULL COLUMNS FROM Usuarios WHERE Field = 'email'");
        if ($row_verify = $result_verify->fetch_assoc()) {
            $colacion_nueva = $row_verify['Collation'] ?? 'N/A';
            echo "<div class='success-box'>";
            echo "<p class='ok'>✅ Colación verificada: <code>" . htmlspecialchars($colacion_nueva) . "</code></p>";
            echo "</div>";
        }
        
        // Verificar que el índice UNIQUE sigue existiendo
        $result_index = $mysqli->query("SHOW INDEX FROM Usuarios WHERE Column_name = 'email'");
        $index_exists = false;
        while ($row_index = $result_index->fetch_assoc()) {
            if ($row_index['Non_unique'] == 0) {
                $index_exists = true;
                break;
            }
        }
        
        if (!$index_exists) {
            echo "<p class='warning'>⚠️ El índice UNIQUE en email parece haberse perdido. Recreándolo...</p>";
            $sql_index = "ALTER TABLE Usuarios ADD UNIQUE KEY email (email)";
            if ($mysqli->query($sql_index)) {
                echo "<p class='ok'>✅ Índice UNIQUE recreado exitosamente</p>";
            } else {
                echo "<p class='error'>❌ Error al recrear índice: " . htmlspecialchars($mysqli->error) . "</p>";
            }
        }
    } else {
        $error_msg = $mysqli->error;
        echo "<p class='error'>❌ Error al cambiar colación: " . htmlspecialchars($error_msg) . "</p>";
        
        // Si el error es por el índice UNIQUE, intentar método alternativo
        if (strpos($error_msg, 'Duplicate entry') !== false || strpos($error_msg, 'UNIQUE') !== false) {
            echo "<p class='warning'>⚠️ Intentando método alternativo (eliminar y recrear índice)...</p>";
            
            // Intentar eliminar índice único si existe
            $mysqli->query("ALTER TABLE Usuarios DROP INDEX email");
            
            // Cambiar colación
            if ($mysqli->query($sql)) {
                echo "<p class='ok'>✅ Colación cambiada exitosamente</p>";
                
                // Recrear índice único
                $sql_index = "ALTER TABLE Usuarios ADD UNIQUE KEY email (email)";
                if ($mysqli->query($sql_index)) {
                    echo "<p class='ok'>✅ Índice UNIQUE recreado exitosamente</p>";
                } else {
                    echo "<p class='error'>❌ Error al recrear índice: " . htmlspecialchars($mysqli->error) . "</p>";
                }
            } else {
                echo "<p class='error'>❌ Error al cambiar colación (método alternativo): " . htmlspecialchars($mysqli->error) . "</p>";
            }
        } else {
            echo "<p><strong>Posibles causas:</strong></p>";
            echo "<ul>";
            echo "<li>Permisos insuficientes</li>";
            echo "<li>Conflicto con datos existentes</li>";
            echo "<li>Tabla bloqueada por otra operación</li>";
            echo "</ul>";
        }
    }
} else {
    echo "<p class='ok'>✅ No se requiere cambio, la colación ya está correcta</p>";
}

// ========================================================================
// 5. VERIFICAR USUARIOS DESPUÉS DEL CAMBIO
// ========================================================================
echo "<h2>5. Verificación de Usuarios Después del Cambio</h2>";

foreach ($usuarios_test as $email) {
    echo "<h3>Usuario: " . htmlspecialchars($email) . "</h3>";
    
    // Probar diferentes formas de búsqueda
    $busquedas = [
        'Normal' => "SELECT id_usuario, email FROM Usuarios WHERE email = ? LIMIT 1",
        'Con TRIM' => "SELECT id_usuario, email FROM Usuarios WHERE TRIM(email) = ? LIMIT 1",
        'Con COLLATE utf8mb4_bin' => "SELECT id_usuario, email FROM Usuarios WHERE email = ? COLLATE utf8mb4_bin LIMIT 1"
    ];
    
    foreach ($busquedas as $nombre => $sql_query) {
        try {
            $stmt = $mysqli->prepare($sql_query);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo "<p class='ok'>✅ Búsqueda '$nombre': Encontrado (ID: " . $row['id_usuario'] . ")</p>";
            } else {
                echo "<p class='error'>❌ Búsqueda '$nombre': No encontrado</p>";
            }
            $stmt->close();
        } catch (Exception $e) {
            echo "<p class='error'>❌ Búsqueda '$nombre': Error - " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    echo "<hr>";
}

// ========================================================================
// 6. PRUEBA DE LOGIN SIMULADO
// ========================================================================
echo "<h2>6. Prueba de Login Simulado</h2>";

$credenciales = [
    'admin@test.com' => 'admin@test.com',
    'admin2@test.com' => 'admin2@test.com'
];

foreach ($credenciales as $email => $password) {
    echo "<h3>Login para: " . htmlspecialchars($email) . "</h3>";
    
    // Simular exactamente lo que hace login.php
    $email_procesado = trim($email);
    $email_procesado = preg_replace('/[\x00-\x1F\x7F]/u', '', $email_procesado);
    
    // Consulta normal (como en login.php)
    $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email_procesado);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['contrasena'])) {
            echo "<p class='ok'>✅✅✅ LOGIN EXITOSO</p>";
            echo "<pre>";
            echo "ID: " . $row['id_usuario'] . "\n";
            echo "Email: " . htmlspecialchars($row['email']) . "\n";
            echo "Rol: " . htmlspecialchars($row['rol']) . "\n";
            echo "</pre>";
        } else {
            echo "<p class='error'>❌ Contraseña incorrecta</p>";
        }
    } else {
        echo "<p class='error'>❌ Usuario no encontrado</p>";
    }
    $stmt->close();
    echo "<hr>";
}

// ========================================================================
// 7. RESUMEN FINAL
// ========================================================================
echo "<h2>7. Resumen Final</h2>";

$result_final = $mysqli->query("SHOW FULL COLUMNS FROM Usuarios WHERE Field = 'email'");
if ($row_final = $result_final->fetch_assoc()) {
    $colacion_final = $row_final['Collation'] ?? 'N/A';
    
    echo "<div class='success-box'>";
    echo "<h3>Estado Final:</h3>";
    echo "<pre>";
    echo "Campo: " . htmlspecialchars($row_final['Field']) . "\n";
    echo "Tipo: " . htmlspecialchars($row_final['Type'] ?? 'N/A') . "\n";
    echo "Colación: <strong>" . htmlspecialchars($colacion_final) . "</strong>\n";
    echo "</pre>";
    
    if ($colacion_final === 'utf8mb4_unicode_ci') {
        echo "<p class='ok'>✅ La colación está correctamente configurada como utf8mb4_unicode_ci</p>";
        echo "<p>Esto garantiza consistencia con el código SQL y las consultas del sistema.</p>";
    } else {
        echo "<p class='warning'>⚠️ La colación es: " . htmlspecialchars($colacion_final) . "</p>";
        echo "<p>Si hay problemas de login, considera usar utf8mb4_bin para comparación exacta.</p>";
    }
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Próximos pasos:</strong></p>";
echo "<ul>";
echo "<li>Prueba hacer login con ambos usuarios en <a href='login.php'>login.php</a></li>";
echo "<li>Si aún hay problemas, ejecuta <a href='diagnostico_login_completo.php'>diagnostico_login_completo.php</a></li>";
echo "<li>Si todo funciona, puedes eliminar este archivo de diagnóstico</li>";
echo "</ul>";

echo "</div></body></html>";

$mysqli->close();
?>

