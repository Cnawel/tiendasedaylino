-- =========================
-- FORMAS DE PAGO INICIALES
-- Tienda Seda y Lino
-- =========================
-- Inserta las formas de pago básicas disponibles en el sistema
-- Ejecutar después de database_estructura.sql

-- Configuración de codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

USE tiendasedaylino_db;

-- Insertar formas de pago
INSERT INTO Forma_Pagos (id_forma_pago, nombre, descripcion) VALUES
(1, 'Transferencia Bancaria', 'Transferencia o depósito bancario. Te enviaremos los datos de la cuenta por email.'),
(2, 'Mercado Pago', 'Pago en línea con tarjeta de crédito, débito o saldo en Mercado Pago.'),
(3, 'Efectivo contra entrega', 'Paga en efectivo al recibir tu pedido. Solo disponible en CABA y GBA.'),
(4, 'Tarjeta de Crédito', 'Pago directo con tarjeta de crédito Visa, MasterCard o American Express.')
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion);

-- Verificar inserción
SELECT * FROM Forma_Pagos;

