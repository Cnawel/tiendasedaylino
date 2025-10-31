-- ========================================================================
-- CORRECCIÓN DE COLACIÓN DEL CAMPO EMAIL
-- ========================================================================
-- Este script corrige la colación del campo email de utf8mb4_0900_ai_ci
-- a utf8mb4_unicode_ci para mantener consistencia con el código
-- ========================================================================

USE tiendasedaylino_db;

-- Verificar colación actual
SHOW FULL COLUMNS FROM Usuarios WHERE Field = 'email';

-- Opción 1: Cambiar a utf8mb4_unicode_ci (case-insensitive, consistente con SQL original)
-- Esta es la opción recomendada para mantener consistencia
ALTER TABLE Usuarios 
MODIFY COLUMN email VARCHAR(150) 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci 
NOT NULL;

-- Verificar el cambio
SHOW FULL COLUMNS FROM Usuarios WHERE Field = 'email';

-- ========================================================================
-- ALTERNATIVA: Si utf8mb4_unicode_ci sigue dando problemas,
-- usar utf8mb4_bin para comparación exacta byte por byte
-- ========================================================================
-- Descomentar las siguientes líneas si necesitas comparación exacta:

-- ALTER TABLE Usuarios 
-- MODIFY COLUMN email VARCHAR(150) 
-- CHARACTER SET utf8mb4 
-- COLLATE utf8mb4_bin 
-- NOT NULL;

-- ========================================================================
-- NOTA: Si tienes un índice UNIQUE en email y MySQL da error,
-- primero elimina el índice, cambia la colación, y luego recrea el índice:
-- ========================================================================

-- Paso 1: Eliminar índice único (si existe)
-- ALTER TABLE Usuarios DROP INDEX email;

-- Paso 2: Cambiar colación
-- ALTER TABLE Usuarios 
-- MODIFY COLUMN email VARCHAR(150) 
-- CHARACTER SET utf8mb4 
-- COLLATE utf8mb4_unicode_ci 
-- NOT NULL;

-- Paso 3: Recrear índice único
-- ALTER TABLE Usuarios ADD UNIQUE KEY email (email);

