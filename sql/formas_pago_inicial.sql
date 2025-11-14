-- =========================
-- FORMAS DE PAGO INICIALES
-- Tienda Seda y Lino
-- =========================
-- Inserta las formas de pago básicas disponibles en el sistema
-- Ejecutar después de database_estructura.sql
-- 
-- Este script está actualizado con la estructura normalizada de database_estructura.sql
-- Incluye campo de soft delete (activo) para desactivar formas de pago sin eliminarlas
-- =========================

-- Configuración de codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

USE tiendasedaylino_db;

-- Insertar formas de pago
-- El campo activo tiene valor DEFAULT 1, pero lo especificamos explícitamente para claridad
INSERT INTO Forma_Pagos (id_forma_pago, nombre, descripcion, activo) VALUES
(1, 'Transferencia Bancaria', 'Transferencia o depósito bancario. CBU: ', 1),
(2, 'Mercado Pago', 'Tarjeta de crédito, débito o saldo en Mercado Pago. ALIAS:', 1),
(3, 'Efectivo contra entrega', 'Paga en efectivo al recibir tu pedido. Solo disponible en CABA y GBA.', 1)
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- Verificar inserción
-- Mostrar todas las formas de pago activas e inactivas para referencia
SELECT 
    id_forma_pago, 
    nombre, 
    descripcion, 
    activo,
    CASE 
        WHEN activo = 1 THEN 'Activa'
        ELSE 'Inactiva'
    END AS estado_descripcion
FROM Forma_Pagos
ORDER BY id_forma_pago;
