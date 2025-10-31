-- ========================================================================
-- RECREAR USUARIO ADMIN - Eliminar y crear de nuevo
-- ========================================================================
-- Este script elimina el usuario admin@test.com y lo recrea con
-- la contraseña correctamente hasheada
-- ========================================================================

USE tiendasedaylino_db;

-- Paso 1: Eliminar usuario admin si existe
DELETE FROM Usuarios WHERE email = 'admin@test.com';

-- Paso 2: Crear usuario admin con contraseña hasheada correctamente
-- Contraseña: admin@test.com
-- Hash generado con: password_hash('admin@test.com', PASSWORD_BCRYPT)
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro)
VALUES (
    'Administrador',
    'Sistema',
    'admin@test.com',
    '$2y$10$fQxWVxsEK2IM3NTT4Izcwe/qA9WNf3i7uO6w.3Rfk5cqW2ceFikpa',
    'admin',
    NOW()
);

-- Paso 3: Verificar que se creó correctamente
SELECT id_usuario, nombre, apellido, email, rol, fecha_registro 
FROM Usuarios 
WHERE email = 'admin@test.com';

