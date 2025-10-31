-- =========================
-- CONVERSIÓN A UTF-8 - HOSTING
-- Tienda Seda y Lino
-- Base de datos: if0_40082852_tiendasedaylino_db
-- =========================
-- Script para convertir tablas existentes con datos a UTF-8
-- Ejecutar después de tener datos cargados en las tablas
-- IMPORTANTE: Hacer backup antes de ejecutar

-- Configuración de codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

USE if0_40082852_tiendasedaylino_db;

-- =========================
-- 1. Convertir base de datos completa
-- =========================
-- NOTA: En algunos hostings no se puede modificar el charset de la BD
-- Si da error, continuar con la conversión de tablas
ALTER DATABASE if0_40082852_tiendasedaylino_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- =========================
-- 2. Convertir tabla Usuarios
-- =========================
-- CONVERT TO convierte automáticamente todas las columnas de texto
ALTER TABLE Usuarios 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- 3. Convertir tabla Categorias
-- =========================
ALTER TABLE Categorias 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- 4. Convertir tabla Productos
-- =========================
ALTER TABLE Productos 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- 5. Convertir tabla Fotos_Producto
-- =========================
ALTER TABLE Fotos_Producto 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Si la columna color existe, convertirla también
-- Verificar primero: SHOW COLUMNS FROM Fotos_Producto LIKE 'color';
-- Si existe, descomentar la siguiente línea:
-- ALTER TABLE Fotos_Producto MODIFY color VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;

-- =========================
-- 6. Convertir tabla Stock_Variantes
-- =========================
ALTER TABLE Stock_Variantes 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- 7. Convertir tabla Pedidos
-- =========================
ALTER TABLE Pedidos 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- 8. Convertir tabla Detalle_Pedido
-- =========================
ALTER TABLE Detalle_Pedido 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- 9. Convertir tabla Forma_Pagos
-- =========================
ALTER TABLE Forma_Pagos 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- 10. Convertir tabla Pagos
-- =========================
ALTER TABLE Pagos 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- 11. Convertir tabla Movimientos_Stock
-- =========================
ALTER TABLE Movimientos_Stock 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================
-- VERIFICACIÓN
-- =========================
-- Verificar charset de las tablas
SELECT 
    TABLE_NAME,
    TABLE_COLLATION 
FROM 
    information_schema.TABLES 
WHERE 
    TABLE_SCHEMA = 'if0_40082852_tiendasedaylino_db';

-- Verificar charset de las columnas de texto
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CHARACTER_SET_NAME,
    COLLATION_NAME
FROM 
    information_schema.COLUMNS 
WHERE 
    TABLE_SCHEMA = 'if0_40082852_tiendasedaylino_db' 
    AND CHARACTER_SET_NAME IS NOT NULL
ORDER BY 
    TABLE_NAME, COLUMN_NAME;

