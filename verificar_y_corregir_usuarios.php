<?php
/**
 * ========================================================================
 * VERIFICAR Y CORREGIR USUARIOS ADMIN
 * ========================================================================
 * Verifica y corrige problemas con los usuarios admin
 * ========================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

$usuarios_a_verificar = [
    'admin@test.com' => 'admin@test.com',
    'admin2@test.com' => 'admin2@test.com'
];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Verificar y Corregir Usuarios</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
    h1 { color: #333; }
    .ok { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 5px; }
    .usuario { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔧 Verificar y Corregir Usuarios Admin</h1>";
echo "<hr>";

foreach ($usuarios_a_verificar as $email => $password) {
    echo "<div class='usuario'>";
    echo "<h2>Usuario: " . htmlspecialchars($email) . "</h2>";
    
    // Buscar usuario
    $stmt = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "<p class='ok'>✅ Usuario encontrado en BD</p>";
        echo "<pre>";
        echo "ID: " . $row['id_usuario'] . "\n";
        echo "Email en BD: [" . htmlspecialchars($row['email']) . "]\n";
        echo "Email bytes: " . bin2hex($row['email']) . "\n";
        echo "Email length: " . strlen($row['email']) . "\n";
        echo "Rol: " . htmlspecialchars($row['rol']) . "\n";
        echo "</pre>";
        
        // Verificar contraseña
        $password_ok = password_verify($password, $row['contrasena']);
        echo "<p><strong>Verificación de contraseña:</strong> " . ($password_ok ? "<span class='ok'>✅ CORRECTA</span>" : "<span class='error'>❌ INCORRECTA</span>") . "</p>";
        
        // Si la contraseña no funciona, actualizarla
        if (!$password_ok) {
            echo "<p class='warning'>⚠️ Actualizando contraseña...</p>";
            $new_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt_update = $mysqli->prepare("UPDATE Usuarios SET contrasena = ? WHERE email = ?");
            $stmt_update->bind_param('ss', $new_hash, $email);
            
            if ($stmt_update->execute()) {
                echo "<p class='ok'>✅ Contraseña actualizada correctamente</p>";
                
                // Verificar nuevamente
                $stmt_verify = $mysqli->prepare("SELECT contrasena FROM Usuarios WHERE email = ? LIMIT 1");
                $stmt_verify->bind_param('s', $email);
                $stmt_verify->execute();
                $result_verify = $stmt_verify->get_result();
                if ($row_verify = $result_verify->fetch_assoc()) {
                    if (password_verify($password, $row_verify['contrasena'])) {
                        echo "<p class='ok'>✅✅ Verificación post-actualización: CORRECTA</p>";
                    }
                }
                $stmt_verify->close();
            } else {
                echo "<p class='error'>❌ Error al actualizar contraseña: " . htmlspecialchars($mysqli->error) . "</p>";
            }
            $stmt_update->close();
        }
        
        // Asegurar que el rol sea admin
        if (strtolower(trim($row['rol'])) !== 'admin') {
            echo "<p class='warning'>⚠️ Rol no es 'admin', actualizando...</p>";
            $stmt_rol = $mysqli->prepare("UPDATE Usuarios SET rol = 'admin' WHERE email = ?");
            $stmt_rol->bind_param('s', $email);
            
            if ($stmt_rol->execute()) {
                echo "<p class='ok'>✅ Rol actualizado a 'admin'</p>";
            } else {
                echo "<p class='error'>❌ Error al actualizar rol: " . htmlspecialchars($mysqli->error) . "</p>";
            }
            $stmt_rol->close();
        }
        
        // Verificar si hay espacios o caracteres invisibles en el email
        $email_bd = $row['email'];
        $email_trim = trim($email_bd);
        
        if ($email_bd !== $email_trim) {
            echo "<p class='warning'>⚠️ Email tiene espacios al inicio/final, limpiando...</p>";
            $stmt_clean = $mysqli->prepare("UPDATE Usuarios SET email = ? WHERE id_usuario = ?");
            $stmt_clean->bind_param('si', $email_trim, $row['id_usuario']);
            
            if ($stmt_clean->execute()) {
                echo "<p class='ok'>✅ Email limpiado correctamente</p>";
            } else {
                echo "<p class='error'>❌ Error al limpiar email: " . htmlspecialchars($mysqli->error) . "</p>";
            }
            $stmt_clean->close();
        }
        
        // Prueba de login simulado
        echo "<h3>Prueba de Login Simulado:</h3>";
        $email_test = trim($email);
        $email_test = preg_replace('/[\x00-\x1F\x7F]/u', '', $email_test);
        
        $stmt_test = $mysqli->prepare("SELECT id_usuario, nombre, apellido, email, contrasena, rol FROM Usuarios WHERE email = ? LIMIT 1");
        $stmt_test->bind_param('s', $email_test);
        $stmt_test->execute();
        $result_test = $stmt_test->get_result();
        
        if ($row_test = $result_test->fetch_assoc()) {
            if (password_verify($password, $row_test['contrasena'])) {
                echo "<p class='ok'>✅✅✅ LOGIN SIMULADO EXITOSO</p>";
            } else {
                echo "<p class='error'>❌ LOGIN SIMULADO FALLIDO - Contraseña incorrecta</p>";
            }
        } else {
            echo "<p class='error'>❌ LOGIN SIMULADO FALLIDO - Usuario no encontrado con email procesado</p>";
            echo "<p>Email procesado: [" . htmlspecialchars($email_test) . "] (bytes: " . bin2hex($email_test) . ")</p>";
        }
        $stmt_test->close();
        
    } else {
        echo "<p class='error'>❌ Usuario NO encontrado en BD</p>";
        echo "<p class='warning'>⚠️ Creando usuario...</p>";
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $nombre = 'Administrador';
        $apellido = 'Sistema';
        
        $stmt_insert = $mysqli->prepare("INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro) VALUES (?, ?, ?, ?, 'admin', NOW())");
        $stmt_insert->bind_param('ssss', $nombre, $apellido, $email, $password_hash);
        
        if ($stmt_insert->execute()) {
            echo "<p class='ok'>✅ Usuario creado correctamente</p>";
        } else {
            echo "<p class='error'>❌ Error al crear usuario: " . htmlspecialchars($mysqli->error) . "</p>";
        }
        $stmt_insert->close();
    }
    
    $stmt->close();
    echo "</div>";
    echo "<hr>";
}

echo "<h2>Resumen Final</h2>";
echo "<p>Verificación completada. Revisa los resultados arriba.</p>";
echo "<p><a href='login.php'>← Volver al Login</a> | <a href='diagnostico_login_completo.php'>Ver Diagnóstico Completo</a></p>";

echo "</div></body></html>";

$mysqli->close();
?>

