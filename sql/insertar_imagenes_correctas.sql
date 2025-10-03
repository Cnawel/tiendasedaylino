-- ============================================================================
-- SCRIPT DE INSERCIÓN DE IMÁGENES CORRECTAS
-- ============================================================================
-- 
-- Este script actualiza las imágenes de productos basándose en los archivos
-- disponibles en la carpeta imagenes/productos/
-- 
-- ESTRUCTURA DE IMÁGENES:
-- - foto_prod_miniatura: Imagen pequeña para listados y carritos
-- - foto1_prod: Imagen principal del producto (más importante)
-- - foto2_prod: Imagen secundaria (diferente ángulo o color)
-- - foto3_prod: Imagen terciaria (detalle o variante)
-- 
-- NOTA: Las rutas son relativas al directorio raíz del sitio web
-- ============================================================================

-- Eliminar registros existentes de fotos para reinsertar con datos correctos
DELETE FROM Fotos_Producto;

-- ============================================================================
-- INSERTAR FOTOS DE PRODUCTOS
-- ============================================================================

-- PRODUCTO 1: Camisa Mujer Lino Natural (ID: 1) - GÉNERO: MUJER
INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod)
VALUES (
    1, -- ID del producto
    'imagenes/productos/camisas/camisa_mujer_lino_modelo.png', -- Miniatura (MUJER)
    'imagenes/productos/camisas/camisa_mujer_lino_modelo.png', -- Imagen principal (MUJER)
    'imagenes/productos/camisas/camisa_mujer_lino_modelocerca.png', -- Imagen cercana (MUJER)
    'imagenes/productos/camisas/camisa_grupal.png' -- Imagen grupal (UNISEX)
);

-- PRODUCTO 2: Camisa Hombre Lino Clásico (ID: 2) - GÉNERO: HOMBRE
INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod)
VALUES (
    2, -- ID del producto
    'imagenes/productos/camisas/camisa_hombre_lino_modelo.png', -- Miniatura (HOMBRE)
    'imagenes/productos/camisas/camisa_hombre_lino_modelo.png', -- Imagen principal (HOMBRE)
    'imagenes/productos/camisas/camisa_hombre_lino_modelocerca.png', -- Imagen cercana (HOMBRE)
    'imagenes/productos/camisas/camisa_grupal.png' -- Imagen grupal (UNISEX)
);

-- PRODUCTO 3: Pantalón Mujer Lino (ID: 3) - GÉNERO: MUJER
INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod)
VALUES (
    3, -- ID del producto
    'imagenes/productos/pantalones/pantalon_lino_mujer_modelo_gris.png', -- Miniatura (MUJER)
    'imagenes/productos/pantalones/pantalon_lino_mujer_modelo_gris.png', -- Imagen principal (MUJER)
    'imagenes/productos/pantalones/pantalon_lino_mujer_modelo_celeste.png', -- Imagen celeste (MUJER)
    'imagenes/productos/pantalones/pantalon_lino_mujer_modelo_celeste_zoom.png' -- Imagen zoom (MUJER)
);

-- PRODUCTO 4: Pantalón Hombre Lino (ID: 4) - GÉNERO: HOMBRE
INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod)
VALUES (
    4, -- ID del producto
    'imagenes/productos/pantalones/pantalon_hombre_lino_negro.png', -- Miniatura (HOMBRE)
    'imagenes/productos/pantalones/pantalon_hombre_lino_negro.png', -- Imagen principal (HOMBRE)
    'imagenes/productos/pantalones/pantalon_hombre_lino_gris.png', -- Imagen gris (HOMBRE)
    'imagenes/productos/pantalones/pantalon_hombre_lino_azul.png' -- Imagen azul (HOMBRE)
);

-- PRODUCTO 5: Blusa Seda Mujer (ID: 5)
INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod)
VALUES (
    5, -- ID del producto
    'imagenes/productos/blusas/blusa_mujer_beige.png', -- Miniatura
    'imagenes/productos/blusas/blusa_mujer_beige.png', -- Imagen principal
    'imagenes/productos/blusas/blusa_mujer_beige.png', -- Misma imagen (solo hay una)
    NULL -- No hay tercera imagen
);

-- PRODUCTO 6: Shorts Lino Hombre (ID: 6) - GÉNERO: HOMBRE
-- Usar imagen de pantalón HOMBRE como placeholder hasta tener imagen específica de shorts
INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod)
VALUES (
    6, -- ID del producto
    'imagenes/productos/pantalones/pantalon_lino_hombre_modelo_gris.png', -- Miniatura (HOMBRE)
    'imagenes/productos/pantalones/pantalon_lino_hombre_modelo_gris.png', -- Imagen principal (HOMBRE)
    'imagenes/productos/pantalones/pantalon_hombre_lino_azul.png', -- Imagen azul (HOMBRE)
    'imagenes/productos/pantalones/pantalon_grupal.png' -- Imagen grupal (UNISEX)
);

-- PRODUCTO 7: Shorts Lino Mujer (ID: 7) - GÉNERO: MUJER
-- Usar imagen de pantalón MUJER como placeholder hasta tener imagen específica de shorts
INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod)
VALUES (
    7, -- ID del producto
    'imagenes/productos/pantalones/pantalon_lino_mujer_beige.png', -- Miniatura (MUJER)
    'imagenes/productos/pantalones/pantalon_lino_mujer_beige.png', -- Imagen principal (MUJER)
    'imagenes/productos/pantalones/pantalon_lino_mujer_negro.png', -- Imagen negra (MUJER)
    'imagenes/productos/pantalones/pantalon_grupal.png' -- Imagen grupal (UNISEX)
);

-- ============================================================================
-- VERIFICACIÓN DE INSERCIÓN
-- ============================================================================
-- 
-- Para verificar que las imágenes se insertaron correctamente, ejecutar:
-- 
-- SELECT 
--     p.id_producto,
--     p.nombre_producto,
--     fp.foto_prod_miniatura,
--     fp.foto1_prod,
--     fp.foto2_prod,
--     fp.foto3_prod
-- FROM Productos p
-- LEFT JOIN Fotos_Producto fp ON p.id_producto = fp.id_producto
-- ORDER BY p.id_producto;
-- 
-- ============================================================================

-- Mensaje de confirmación
SELECT 'Imágenes insertadas correctamente' AS mensaje;
