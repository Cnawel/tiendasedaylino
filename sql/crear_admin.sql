-- ========================================================================
-- SCRIPT SQL PARA CREAR/ACTUALIZAR USUARIO ADMIN
-- ========================================================================
-- Ejecuta este script en phpMyAdmin o tu cliente MySQL
-- 
-- IMPORTANTE: Este script crea un usuario admin con:
-- Email: admin@test.com
-- Contraseña: admin@test.com (hasheada con bcrypt)
-- ========================================================================

USE tiendasedaylino_db;

-- Hash bcrypt de "admin@test.com" generado con PHP password_hash()
-- Para generar un nuevo hash, ejecuta en PHP:
-- echo password_hash('admin@test.com', PASSWORD_BCRYPT);

-- Eliminar usuario existente si existe (opcional, solo si quieres recrearlo)
-- DELETE FROM Usuarios WHERE email = 'admin@test.com';

-- Insertar o actualizar usuario admin
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro)
VALUES (
    'Administrador',
    'Sistema',
    'admin@test.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Hash de "admin@test.com"
    'admin',
    NOW()
)
ON DUPLICATE KEY UPDATE
    nombre = 'Administrador',
    apellido = 'Sistema',
    contrasena = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    rol = 'admin';

-- Verificar que se creó correctamente
SELECT id_usuario, nombre, apellido, email, rol, fecha_registro 
FROM Usuarios 
WHERE email = 'admin@test.com';

