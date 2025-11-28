-- =========================
-- PRODUCTOS - Tienda Seda y Lino
-- =========================
-- Este script carga productos basado en las imágenes disponibles en imagenes/productos/
-- Productos generados desde productos_carga_masiva.csv
-- 
-- NOTA: Este script está actualizado con la estructura normalizada de database_estructura.sql
-- Incluye campo sku para productos (opcional)
-- =========================

USE tiendasedaylino_db;

-- Configuración de codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

-- =========================
-- INSERTAR CATEGORÍAS (si no existen)
-- =========================
INSERT IGNORE INTO Categorias (nombre_categoria, descripcion_categoria) VALUES
('Camisas', 'Camisas de seda y lino para hombre y mujer'),
('Blusas', 'Blusas elegantes de seda y lino para mujer'),
('Pantalones', 'Pantalones de lino cómodos y elegantes'),
('Shorts', 'Shorts de lino para hombre y mujer');

-- =========================
-- BLUSAS (Mujer)
-- =========================

-- Blusa Manga Larga Azul
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Blusa Manga Larga Azul', 'Blusa de seda manga larga en color azul perfecta para ocasiones elegantes', 20000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Blusas' LIMIT 1), 
 'mujer', 'BLU-MLA-001')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_blusa_azul = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'BLU-MLA-001' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_blusa_azul AND color = 'azul';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_blusa_azul, 
 'imagenes/productos/blusas/azul/blusa_mujer_mangalarga_azul.webp',
 'imagenes/productos/blusas/azul/blusa_azul_modelo.webp',
 'imagenes/productos/blusas/azul/blusa_mujer_mangalarga_azul - Copy.webp',
 'imagenes/productos/blusas/blusa_mujer_modelogrupal_oficina.webp',
 'azul', 1);

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_blusa_azul, 'S', 'azul', 4),
(@producto_blusa_azul, 'M', 'azul', 4),
(@producto_blusa_azul, 'L', 'azul', 4),
(@producto_blusa_azul, 'XL', 'azul', 4)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Blusa Manga Larga Blanca
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Blusa Manga Larga Blanca', 'Blusa de seda manga larga en color blanco elegante y versátil', 19000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Blusas' LIMIT 1), 
 'mujer', 'BLU-MLB-002')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_blusa_blanca = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'BLU-MLB-002' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_blusa_blanca AND color = 'blanca';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_blusa_blanca, 
 'imagenes/productos/blusas/blanca/blusa_crema_mini.jpg.webp',
 'imagenes/productos/blusas/blanca/blusa_crema_modelo.webp',
 'imagenes/productos/blusas/blanca/blusa_crema1.jpg.webp',
 'imagenes/productos/blusas/blusa_mujer_modelogrupal_universidad.webp',
 'blanca', 1);

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_blusa_blanca, 'S', 'blanca', 4),
(@producto_blusa_blanca, 'M', 'blanca', 4),
(@producto_blusa_blanca, 'L', 'blanca', 4),
(@producto_blusa_blanca, 'XL', 'blanca', 4)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Blusa Simple Celeste
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Blusa Simple Celeste', 'Blusa simple de seda en color celeste ideal para el día a día', 18000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Blusas' LIMIT 1), 
 'mujer', 'BLU-SC-003')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_blusa_celeste = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'BLU-SC-003' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_blusa_celeste AND color = 'celeste';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_blusa_celeste, 
 'imagenes/productos/blusas/celeste/blusa_mujer_modelosimple_celeste.webp',
 'imagenes/productos/blusas/celeste/blusa_mujer_modelosimple_celeste.webp',
 NULL,
 'imagenes/productos/blusas/blusa_mujer_modelogrupal_bar.webp',
 'celeste', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_blusa_celeste, 'S', 'celeste', 4),
(@producto_blusa_celeste, 'M', 'celeste', 4),
(@producto_blusa_celeste, 'L', 'celeste', 4),
(@producto_blusa_celeste, 'XL', 'celeste', 4)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- =========================
-- CAMISAS
-- =========================

-- Camisa Hombre Lino Natural
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Camisa Hombre Lino Natural', 'Camisa de lino para hombre en color natural cómoda y fresca', 22000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Camisas' LIMIT 1), 
 'hombre', 'CAM-HLN-001')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_camisa_hombre = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'CAM-HLN-001' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_camisa_hombre AND color = 'natural';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_camisa_hombre, 
 'imagenes/productos/camisas/hombre/natural/camisa_hombre_lino_modelocerca.webp',
 'imagenes/productos/camisas/hombre/natural/camisa_hombre_lino_modelo.webp',
 NULL,
 'imagenes/productos/camisas/camisa_grupal.webp',
 'natural', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_camisa_hombre, 'S', 'natural', 5),
(@producto_camisa_hombre, 'M', 'natural', 5),
(@producto_camisa_hombre, 'L', 'natural', 5),
(@producto_camisa_hombre, 'XL', 'natural', 5)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Camisa Mujer Lino Natural
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Camisa Mujer Lino Natural', 'Camisa de lino para mujer en color natural elegante y versátil', 23000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Camisas' LIMIT 1), 
 'mujer', 'CAM-MLN-002')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_camisa_mujer = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'CAM-MLN-002' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_camisa_mujer AND color = 'natural';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_camisa_mujer, 
 'imagenes/productos/camisas/mujer/natural/camisa_mujer_lino_modelocerca.webp',
 'imagenes/productos/camisas/mujer/natural/camisa_mujer_lino_modelo.webp',
 NULL,
 'imagenes/productos/camisas/camisa_grupal.webp',
 'natural', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_camisa_mujer, 'S', 'natural', 5),
(@producto_camisa_mujer, 'M', 'natural', 5),
(@producto_camisa_mujer, 'L', 'natural', 5),
(@producto_camisa_mujer, 'XL', 'natural', 5)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- =========================
-- PANTALONES HOMBRE
-- =========================

-- Pantalón Hombre Lino Azul
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Pantalón Hombre Lino Azul', 'Pantalón de lino para hombre en color azul cómodo y elegante', 25000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Pantalones' LIMIT 1), 
 'hombre', 'PAN-HLA-001')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_pantalon_hombre_azul = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'PAN-HLA-001' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_pantalon_hombre_azul AND color = 'azul';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_pantalon_hombre_azul, 
 'imagenes/productos/pantalones/hombre/azul/pantalon_hombre_lino_azul.webp',
 'imagenes/productos/pantalones/hombre/azul/pantalon_hombre_lino_azul_modelo2.webp',
 'imagenes/productos/pantalones/hombre/azul/pantalon_hombre_lino_azul_modelo3.webp',
 'imagenes/productos/pantalones/pantalon_lino_grupal_oficina.webp',
 'azul', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_hombre_azul, 'S', 'azul', 5),
(@producto_pantalon_hombre_azul, 'M', 'azul', 5),
(@producto_pantalon_hombre_azul, 'L', 'azul', 5),
(@producto_pantalon_hombre_azul, 'XL', 'azul', 5)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Pantalón Hombre Lino Gris
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Pantalón Hombre Lino Gris', 'Pantalón de lino para hombre en color gris versátil y clásico', 26000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Pantalones' LIMIT 1), 
 'hombre', 'PAN-HLG-002')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_pantalon_hombre_gris = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'PAN-HLG-002' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_pantalon_hombre_gris AND color = 'gris';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_pantalon_hombre_gris, 
 'imagenes/productos/pantalones/hombre/gris/pantalon_hombre_lino_gris.webp',
 'imagenes/productos/pantalones/hombre/gris/pantalon_hombre_lino_gris_modelo.webp',
 'imagenes/productos/pantalones/hombre/gris/pantalon_lino_hombre_gris_modelo2.webp',
 'imagenes/productos/pantalones/pantalon_lino_grupal_exterior.webp',
 'gris', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_hombre_gris, 'S', 'gris', 5),
(@producto_pantalon_hombre_gris, 'M', 'gris', 5),
(@producto_pantalon_hombre_gris, 'L', 'gris', 5),
(@producto_pantalon_hombre_gris, 'XL', 'gris', 5)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Pantalón Hombre Lino Negro
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Pantalón Hombre Lino Negro', 'Pantalón de lino para hombre en color negro elegante y sofisticado', 27000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Pantalones' LIMIT 1), 
 'hombre', 'PAN-HLN-003')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_pantalon_hombre_negro = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'PAN-HLN-003' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_pantalon_hombre_negro AND color = 'negro';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_pantalon_hombre_negro, 
 'imagenes/productos/pantalones/hombre/negro/pantalon_hombre_lino_negro.webp',
 'imagenes/productos/pantalones/hombre/negro/pantalon_hombre_lino_negro.webp',
 NULL,
 'imagenes/productos/pantalones/pantalon_grupal.webp',
 'negro', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_hombre_negro, 'S', 'negro', 5),
(@producto_pantalon_hombre_negro, 'M', 'negro', 5),
(@producto_pantalon_hombre_negro, 'L', 'negro', 5),
(@producto_pantalon_hombre_negro, 'XL', 'negro', 5)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- =========================
-- SHORTS
-- =========================

-- Short Hombre Marrón
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Short Hombre Marrón', 'Short de lino para hombre en color marrón ideal para el verano', 16000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'hombre', 'SHR-HM-001')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_short_hombre_marron = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'SHR-HM-001' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_short_hombre_marron AND color = 'marron';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_short_hombre_marron, 
 'imagenes/productos/shorts/hombre/marron/short_hombre_marron_mini.jpg.webp',
 'imagenes/productos/shorts/hombre/marron/short_hombre_marron.webp',
 'imagenes/productos/shorts/hombre/marron/short_hombre_marron1.jpg.webp',
 NULL,
 'marron', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_short_hombre_marron, 'S', 'marron', 3),
(@producto_short_hombre_marron, 'M', 'marron', 3),
(@producto_short_hombre_marron, 'L', 'marron', 3),
(@producto_short_hombre_marron, 'XL', 'marron', 3)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Short Hombre Negro
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Short Hombre Negro', 'Short de lino para hombre en color negro versátil y cómodo', 15000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'hombre', 'SHR-HN-002')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_short_hombre_negro = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'SHR-HN-002' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_short_hombre_negro AND color = 'negro';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_short_hombre_negro, 
 'imagenes/productos/shorts/hombre/Negro/short_hombre_negro_mini.webp',
 'imagenes/productos/shorts/hombre/Negro/short_hombre_negro.webp',
 NULL,
 NULL,
 'negro', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_short_hombre_negro, 'S', 'negro', 3),
(@producto_short_hombre_negro, 'M', 'negro', 3),
(@producto_short_hombre_negro, 'L', 'negro', 3),
(@producto_short_hombre_negro, 'XL', 'negro', 3)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Pantalón Mujer Celeste
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Pantalón Mujer Celeste', 'Pantalón de lino para mujer en color celeste fresco y elegante', 17000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'mujer', 'PAN-MC-003')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_pantalon_mujer_celeste = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'PAN-MC-003' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_pantalon_mujer_celeste AND color = 'celeste';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_pantalon_mujer_celeste, 
 'imagenes/productos/shorts/mujer/celeste/pantalon_mujer_celeste.webp',
 'imagenes/productos/shorts/mujer/celeste/pantalon_mujer_celeste_modelo.webp',
 NULL,
 'imagenes/productos/shorts/pantalon_mujer_grupal.webp',
 'celeste', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_mujer_celeste, 'S', 'celeste', 3),
(@producto_pantalon_mujer_celeste, 'M', 'celeste', 3),
(@producto_pantalon_mujer_celeste, 'L', 'celeste', 3),
(@producto_pantalon_mujer_celeste, 'XL', 'celeste', 3)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Pantalón Mujer Crema
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Pantalón Mujer Crema', 'Pantalón de lino para mujer en color crema suave y elegante', 18000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'mujer', 'PAN-MCR-004')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_pantalon_mujer_crema = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'PAN-MCR-004' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_pantalon_mujer_crema AND color = 'crema';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_pantalon_mujer_crema, 
 'imagenes/productos/shorts/mujer/crema/pantalon_mujer_crema_mini.webp',
 'imagenes/productos/shorts/mujer/crema/pantalon_mujer_crema_modelo.webp',
 'imagenes/productos/shorts/mujer/crema/pantalon_mujer_crema.webp',
 'imagenes/productos/shorts/pantalon_mujer_grupal_noche.webp',
 'crema', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_mujer_crema, 'S', 'crema', 3),
(@producto_pantalon_mujer_crema, 'M', 'crema', 3),
(@producto_pantalon_mujer_crema, 'L', 'crema', 3),
(@producto_pantalon_mujer_crema, 'XL', 'crema', 3)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- Short Mujer Negro
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero, sku) VALUES
('Short Mujer Negro', 'Short de lino para mujer en color negro versátil y cómodo', 14000.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'mujer', 'SHR-MN-005')
ON DUPLICATE KEY UPDATE nombre_producto = nombre_producto;

SET @producto_short_mujer_negro = COALESCE((SELECT id_producto FROM Productos WHERE sku = 'SHR-MN-005' LIMIT 1), LAST_INSERT_ID());

-- Eliminar fotos existentes si el producto ya existe
DELETE FROM Fotos_Producto WHERE id_producto = @producto_short_mujer_negro AND color = 'negro';

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color, activo) VALUES
(@producto_short_mujer_negro, 
 'imagenes/productos/shorts/mujer/negro/short_mujer_negro_mini_modelo.webp',
 'imagenes/productos/shorts/mujer/negro/short_mujer_negro_mini.jpg.webp',
 'imagenes/productos/shorts/mujer/negro/short_mujer_negro2.jpg.webp',
 NULL,
 'negro', 1)

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_short_mujer_negro, 'S', 'negro', 1),
(@producto_short_mujer_negro, 'M', 'negro', 1),
(@producto_short_mujer_negro, 'L', 'negro', 1),
(@producto_short_mujer_negro, 'XL', 'negro', 1)
ON DUPLICATE KEY UPDATE stock = VALUES(stock);

-- =========================
-- RESUMEN
-- =========================
-- Total productos insertados: 13
-- - 3 Blusas (mujer)
-- - 2 Camisas (hombre y mujer)
-- - 3 Pantalones (hombre)
-- - 5 Shorts/Pantalones (2 hombre, 3 mujer)
-- 
-- Total variantes: 52 (13 productos × 4 talles)
-- Total stock: 200 unidades exactas
-- 
-- Precios: $14,000 - $27,000 según categoría
-- 
-- Notas sobre imágenes:
-- - Todas las rutas de imágenes usan extensión .webp
-- - Las fotos están organizadas por categoría/color/género
-- - foto_prod_miniatura: Imagen principal del producto
-- - foto1_prod, foto2_prod: Fotos adicionales del producto
-- - foto3_prod: Fotos grupales cuando están disponibles
-- =========================

