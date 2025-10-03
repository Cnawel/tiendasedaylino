-- ============================================================================
-- USUARIOS DE PRUEBA CON CONTRASEÑAS HASHEADAS
-- ============================================================================
-- 
-- Este archivo contiene usuarios de prueba con contraseñas hasheadas correctamente
-- para que funcionen con el sistema de login
-- 
-- CONTRASEÑAS ORIGINALES:
-- - pass123 (para clientes y empleados)
-- - admin123 (para administrador)
-- 
-- NOTA: Las contraseñas están hasheadas con password_hash() de PHP
-- ============================================================================

-- Eliminar usuarios existentes para reinsertar con contraseñas correctas
DELETE FROM Usuarios;

-- ============================================================================
-- INSERTAR USUARIOS DE PRUEBA
-- ============================================================================

-- USUARIO 1: Cliente de prueba
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, telefono, direccion, localidad, provincia, codigo_postal, fecha_registro)
VALUES (
    'Ana', 
    'Martínez', 
    'cliente@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- pass123
    'cliente', 
    '1122334455', 
    'Calle 123', 
    'CABA', 
    'Buenos Aires', 
    '1000', 
    NOW()
);

-- USUARIO 2: Administrador
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, telefono, direccion, localidad, provincia, codigo_postal, fecha_registro)
VALUES (
    'Carlos', 
    'Administrador', 
    'admin@example.com', 
    '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', -- admin123
    'admin', 
    '1133445566', 
    'Av. Siempreviva 742', 
    'CABA', 
    'Buenos Aires', 
    '1001', 
    NOW()
);

-- USUARIO 3: Ventas
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, telefono, direccion, localidad, provincia, codigo_postal, fecha_registro)
VALUES (
    'Lucía', 
    'Fernández', 
    'ventas@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- pass123
    'ventas', 
    '1144556677', 
    'Mitre 500', 
    'Rosario', 
    'Santa Fe', 
    '2000', 
    NOW()
);

-- USUARIO 4: Marketing
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, telefono, direccion, localidad, provincia, codigo_postal, fecha_registro)
VALUES (
    'Javier', 
    'Pérez', 
    'marketing@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- pass123
    'marketing', 
    '1155667788', 
    'Belgrano 800', 
    'La Plata', 
    'Buenos Aires', 
    '1900', 
    NOW()
);

-- USUARIO 5: Cliente adicional
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, telefono, direccion, localidad, provincia, codigo_postal, fecha_registro)
VALUES (
    'María', 
    'González', 
    'maria.gonzalez@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- pass123
    'cliente', 
    '1166778899', 
    'San Martín 300', 
    'Córdoba', 
    'Córdoba', 
    '5000', 
    NOW()
);

-- USUARIO 6: Cliente adicional
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, telefono, direccion, localidad, provincia, codigo_postal, fecha_registro)
VALUES (
    'Roberto', 
    'Silva', 
    'roberto.silva@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- pass123
    'cliente', 
    '1177889900', 
    'Rivadavia 1500', 
    'Mendoza', 
    'Mendoza', 
    '5500', 
    NOW()
);

-- ============================================================================
-- VERIFICACIÓN DE USUARIOS INSERTADOS
-- ============================================================================
-- 
-- Para verificar que los usuarios se insertaron correctamente, ejecutar:
-- 
-- SELECT 
--     id_usuario,
--     nombre,
--     apellido,
--     email,
--     rol,
--     telefono,
--     localidad,
--     provincia
-- FROM Usuarios
-- ORDER BY rol, nombre;
-- 
-- ============================================================================

-- Mensaje de confirmación
SELECT 'Usuarios de prueba insertados correctamente' AS mensaje;

