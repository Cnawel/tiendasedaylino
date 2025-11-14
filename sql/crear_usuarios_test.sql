-- ========================================================================
-- SCRIPT SQL PARA CREAR USUARIOS DE PRUEBA - Tienda Seda y Lino
-- ========================================================================
-- Ejecuta este script en phpMyAdmin o tu cliente MySQL
-- 
-- Este script está actualizado con la estructura normalizada de database_estructura.sql
-- Incluye campos de auditoría (fecha_actualizacion) y soft delete (activo)
-- 
-- USUARIOS CREADOS:
-- - Email: ventas@test.com | Contraseña: ventas@test.com | Rol: ventas
-- - Email: marketing@test.com | Contraseña: marketing@test.com | Rol: marketing
-- - Email: admin@test.com | Contraseña: admin@test.com | Rol: admin
-- - Email: cliente@test.com | Contraseña: cliente@test.com | Rol: cliente
-- ========================================================================
-- 
-- IMPORTANTE: 
-- Este archivo se genera automáticamente con hashes válidos usando PASSWORD_DEFAULT.
-- Para regenerarlo, ejecuta: php sql/generar_usuarios_test.php
-- 
-- Los hashes se generan con password_hash($password, PASSWORD_DEFAULT) que:
-- - Usa el algoritmo más seguro disponible automáticamente
-- - Es compatible con versiones futuras de PHP
-- - Genera hashes únicos cada vez (pero todos son válidos para la misma contraseña)
-- 
-- NOTA SOBRE LOS HASHES:
-- Cada hash debe ser único para cada usuario, incluso si tienen la misma contraseña.
-- El hash se genera usando las mismas funciones que register.php y admin.php:
-- - generarHashPassword() -> password_hash($password, PASSWORD_DEFAULT)
-- - verificarPassword() -> password_verify($password, $hash)
-- El hash debe tener al menos 60 caracteres para bcrypt, pero puede variar según el algoritmo.
-- ========================================================================

USE tiendasedaylino_db;

-- Configuración de codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

-- ========================================================================
-- Usuario: ventas@test.com (Rol: ventas)
-- Contraseña: ventas@test.com
-- ========================================================================
DELETE FROM Usuarios WHERE email = 'ventas@test.com';

INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro, activo) VALUES (
    'Ventas',
    'Test',
    'ventas@test.com',
    '$2y$10$OZ1vsdbvXZa.jz1DT/TWZeOJ3uO8lWBVf55mIj74Q29HxUKtwgiM.',
    'ventas',
    NOW(),
    1
);

-- ========================================================================
-- Usuario: marketing@test.com (Rol: marketing)
-- Contraseña: marketing@test.com
-- ========================================================================
DELETE FROM Usuarios WHERE email = 'marketing@test.com';

INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro, activo) VALUES (
    'Marketing',
    'Test',
    'marketing@test.com',
    '$2y$10$v6Rt1Kn3CihUAVDrjrjU1.Mtj/3h07.OPIZ5kuXm2LKtofMCO9TfO',
    'marketing',
    NOW(),
    1
);

-- ========================================================================
-- Usuario: admin@test.com (Rol: admin)
-- Contraseña: admin@test.com
-- ========================================================================
DELETE FROM Usuarios WHERE email = 'admin@test.com';

INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro, activo) VALUES (
    'Admin',
    'Test',
    'admin@test.com',
    '$2y$10$QMPf4e3Odw7/r6ZVt6oWpOth6R2LRBeW1D4ji3jXI1sjx8SrxfA/q',
    'admin',
    NOW(),
    1
);

-- ========================================================================
-- Usuario: cliente@test.com (Rol: cliente)
-- Contraseña: cliente@test.com
-- ========================================================================
DELETE FROM Usuarios WHERE email = 'cliente@test.com';

INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro, activo) VALUES (
    'Cliente',
    'Test',
    'cliente@test.com',
    '$2y$10$iEjiKgaZkJI7yZ78EmlmRe0A43Sa1GmkTzwV8xR81cc3i0YdkM8Ai',
    'cliente',
    NOW(),
    1
);

-- ========================================================================
-- VERIFICAR QUE SE CREARON CORRECTAMENTE
-- ========================================================================
SELECT 
    id_usuario, 
    nombre, 
    apellido, 
    email, 
    rol, 
    activo,
    fecha_registro,
    fecha_actualizacion
FROM Usuarios 
WHERE email IN ('ventas@test.com', 'marketing@test.com', 'admin@test.com', 'cliente@test.com')
ORDER BY 
    CASE rol 
        WHEN 'admin' THEN 1 
        WHEN 'ventas' THEN 2 
        WHEN 'marketing' THEN 3 
        WHEN 'cliente' THEN 4 
    END,
    email;
