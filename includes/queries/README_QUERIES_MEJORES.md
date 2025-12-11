# Mejoras Identificadas en Queries SQL

Este documento contiene todas las mejoras identificadas en los archivos de queries, organizadas por categoría (Seguridad, Rendimiento, Abreviaciones).

**Fecha de revisión:** 2024-2025
**Archivos revisados:** 9 archivos de queries (carrito_queries.php está deshabilitado - no se usa)

---

## 📋 Índice

1. [Mejoras de Seguridad](#mejoras-de-seguridad)
2. [Mejoras de Rendimiento](#mejoras-de-rendimiento)
3. [Mejoras de Abreviación](#mejoras-de-abreviación)
4. [Índices Recomendados](#índices-recomendados)

---

## 🔒 Mejoras de Seguridad

### 1. LIMIT Dinámico en Queries

**Archivos afectados:**
- `pedido_queries.php` - función `obtenerPedidos()` (línea 82-88)
- `pago_queries.php` - función `obtenerPagos()` (línea 646-659)

**Problema:**
Aunque el límite está validado con `intval()` y `max()`, se construye directamente en la query SQL en lugar de usar placeholder.

**Mejora propuesta:**
- Usar placeholder para LIMIT (requiere MySQL 5.7.5+ o MariaDB 10.2+)
- Alternativa: Ejecutar dos queries separadas (COUNT y SELECT con LIMIT)
- Alternativa: Usar prepared statement con `bind_result()` para mejor control

**Prioridad:** Media (la validación actual es suficiente, pero podría mejorarse)

---

### 2. Validación de Estado de Pedido en Descuento de Stock

**Archivos afectados:**
- `stock_queries.php` - función `descontarStockPedido()` (línea 331-335)

**Problema:**
No se valida el estado del pedido antes de descontar stock, lo que podría permitir descuentos en pedidos cancelados o ya procesados.

**Mejora propuesta:**
Agregar validación de estado del pedido antes de descontar:
```php
// Verificar que el pedido esté en estado válido para descontar stock
$sql_validar_pedido = "SELECT estado_pedido FROM Pedidos WHERE id_pedido = ?";
// Solo proceder si estado_pedido = 'pendiente' o 'preparacion'
```

**Prioridad:** Alta (previene errores de negocio)

---

### 3. Uso de mysqli->query() para SET Commands

**Archivos afectados:**
- `usuario_queries.php` - funciones `buscarUsuarioPorEmail()` y `obtenerUsuarioPorEmailRecupero()` (líneas 467-468, 636-637)
- `password_functions.php` - función `generarHashPassword()` (líneas 38, 44)

**Problema:**
Se usa `mysqli->query()` directamente para comandos SET (collation, charset). Aunque estos comandos no usan datos del usuario, sería más consistente usar prepared statements o una función centralizada.

**Mejora propuesta:**
1. **Crear función centralizada para configuración de conexión:**
   ```php
   function configurarConexionBD($mysqli) {
       $mysqli->set_charset("utf8mb4");
       $mysqli->query("SET collation_connection = 'utf8mb4_unicode_ci'");
       $mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
   }
   ```

2. **Nota:** Los comandos SET son seguros ya que no usan datos del usuario, pero la centralización mejora mantenibilidad.

**Prioridad:** Baja (seguridad actual es adecuada, mejora de mantenibilidad)

---

## ⚡ Mejoras de Rendimiento

### 1. Subconsultas Múltiples Anidadas en Productos

**Archivos afectados:**
- `producto_queries.php` - función `_construirQueryDatosCompletos()` (línea 971-1092)

**Problema:**
La función tiene múltiples subconsultas anidadas en `COALESCE` que se ejecutan secuencialmente:
- Subconsulta para color
- Subconsulta para stock
- 3 subconsultas anidadas para fotos (una dentro de otra)

**Mejora propuesta:**
1. **Usar JOINs en lugar de subconsultas correlacionadas:**
   ```sql
   LEFT JOIN (
       SELECT id_producto, MIN(color) as color, SUM(stock) as total_stock
       FROM Stock_Variantes
       WHERE activo = 1 AND stock > 0
       GROUP BY id_producto
   ) sv_summary ON p.id_producto = sv_summary.id_producto
   ```

2. **Usar CTEs (Common Table Expressions) en MySQL 8.0+:**
   ```sql
   WITH stock_summary AS (
       SELECT id_producto, MIN(color) as color, SUM(stock) as total_stock
       FROM Stock_Variantes
       WHERE activo = 1 AND stock > 0
       GROUP BY id_producto
   )
   SELECT ...
   FROM Productos p
   LEFT JOIN stock_summary ON p.id_producto = stock_summary.id_producto
   ```

**Impacto esperado:** Reducción del 40-60% en tiempo de ejecución para catálogos grandes

**Prioridad:** Alta

---

### 2. Índices Compuestos Faltantes

**Archivos afectados:**
- `producto_queries.php` - múltiples funciones
- `pedido_queries.php` - función `obtenerPedidosPorUsuario()` (línea 497-509)

**Problemas identificados:**

1. **Stock_Variantes:**
   - Falta índice compuesto para optimizar búsquedas por producto, activo, stock y talle
   - **Recomendación:** `CREATE INDEX idx_stock_variantes_producto_activo_stock ON Stock_Variantes(id_producto, activo, stock, talle, color);`

2. **Detalle_Pedido:**
   - Falta índice compuesto para cálculos de totales
   - **Recomendación:** `CREATE INDEX idx_detalle_pedido_calculo_total ON Detalle_Pedido(id_pedido, cantidad, precio_unitario);`

3. **Fotos_Producto:**
   - Falta índice compuesto para búsquedas por producto y activo
   - **Recomendación:** `CREATE INDEX idx_fotos_producto_producto_activo ON Fotos_Producto(id_producto, activo, color);`

**Impacto esperado:** Reducción del 30-50% en tiempo de ejecución de queries complejas

**Prioridad:** Media-Alta

---

### 3. FOR UPDATE en Queries de Validación

**Archivos afectados:**
- `pago_queries.php` - función `actualizarEstadoPago()` y `actualizarPagoCompleto()` (líneas 230, 412, 458)
- `stock_queries.php` - función `validarAjusteStock()` y `validarStockDisponibleVenta()` (líneas 37, 88, 369)

**Problema:**
`FOR UPDATE` bloquea filas hasta el commit de la transacción. Aunque es necesario para prevenir race conditions, podría optimizarse.

**Mejora propuesta:**
1. **Para pagos únicos aprobados:**
   - Crear índice único parcial: `CREATE UNIQUE INDEX idx_pagos_pedido_aprobado ON Pagos(id_pedido, estado_pago) WHERE estado_pago = 'aprobado';`
   - Esto garantiza unicidad a nivel de BD y elimina necesidad de verificación con FOR UPDATE

2. **Verificar que la conexión esté en modo transaccional:**
   ```php
   if (!$mysqli->in_transaction) {
       throw new Exception('FOR UPDATE requiere transacción activa');
   }
   ```

**Impacto esperado:** Reducción del tiempo de bloqueo y mejor concurrencia

**Prioridad:** Media

---

### 4. Múltiples Búsquedas de Email

**Archivos afectados:**
- `usuario_queries.php` - funciones `buscarUsuarioPorEmail()` y `obtenerUsuarioPorEmailRecupero()` (líneas 605-692, 447-502)

**Problema:**
Ambas funciones realizan múltiples intentos de búsqueda (hasta 3 intentos) para compatibilidad con emails mal formateados en la BD.

**Mejora propuesta:**
1. **Normalizar emails en la base de datos:**
   - Agregar trigger o lógica en PHP para normalizar emails al insertar/actualizar
   - Esto eliminaría la necesidad de múltiples búsquedas

2. **Crear columna email_normalizado:**
   ```sql
   ALTER TABLE Usuarios ADD COLUMN email_normalizado VARCHAR(255) AS (LOWER(TRIM(email))) STORED;
   CREATE UNIQUE INDEX idx_usuarios_email_normalizado ON Usuarios(email_normalizado);
   ```

**Impacto esperado:** Reducción del 66% en tiempo de búsqueda (de 3 intentos a 1)

**Prioridad:** Media

---

### 5. Cálculo de Totales en Pedidos

**Archivos afectados:**
- `pedido_queries.php` - función `obtenerPedidos()` (línea 90-102)
- `pedido_queries.php` - función `obtenerPedidosPorUsuario()` (línea 498-509)
- `perfil_queries.php` - función `obtenerPedidosUsuario()` (línea 218-244)

**Problema:**
Las queries calculan totales usando `SUM()` y `COALESCE()` con múltiples JOINs, lo cual puede ser costoso. La función `obtenerPedidosUsuario()` también calcula devoluciones usando subconsultas.

**Mejora propuesta:**
1. **Usar campo `total` de la tabla Pedidos cuando esté disponible:**
   - El campo `total` ya existe en la tabla Pedidos
   - Solo calcular si `total` es NULL

2. **Crear vista materializada o tabla de resumen:**
   ```sql
   CREATE VIEW vw_pedidos_totales AS
   SELECT 
       p.id_pedido,
       COALESCE(p.total, SUM(dp.cantidad * dp.precio_unitario)) as total_calculado
   FROM Pedidos p
   LEFT JOIN Detalle_Pedido dp ON p.id_pedido = dp.id_pedido
   GROUP BY p.id_pedido;
   ```

3. **Optimizar cálculo de devoluciones:**
   - Considerar agregar columna calculada o vista para devoluciones por pedido

**Impacto esperado:** Reducción del 20-30% en tiempo de ejecución

**Prioridad:** Baja-Media

---

### 6. Queries con LIMIT usando placeholder

**Archivos afectados:**
- `producto_queries.php` - función `obtenerProductosRelacionados()` (línea 585)
- `producto_queries.php` - función `obtenerProductosMarketing()` (línea 1812)
- `pedido_queries.php` - función `obtenerPedidosTiempoEstado()` (línea 602)
- `pedido_queries.php` - función `obtenerTopProductosVendidos()` (línea 649)

**Problema:**
Algunas funciones usan placeholder correctamente para LIMIT, pero otras (`obtenerPedidos()`, `obtenerPagos()`) usan concatenación directa aunque está validada.

**Mejora propuesta:**
- Estandarizar el uso de placeholder para LIMIT en todas las funciones
- MySQL 5.7.5+ y MariaDB 10.2+ soportan placeholder en LIMIT

**Prioridad:** Media (consistencia)

---

## ✂️ Mejoras de Abreviación

### 1. Código Duplicado en Búsqueda de Email

**Archivos afectados:**
- `usuario_queries.php` - funciones `buscarUsuarioPorEmail()` y `obtenerUsuarioPorEmailRecupero()`

**Problema:**
Ambas funciones tienen código duplicado para:
- Normalización de email
- Configuración de conexión BD
- Múltiples intentos de búsqueda

**Mejora propuesta:**
Crear funciones auxiliares reutilizables:
```php
/**
 * Normaliza un email para búsqueda
 */
function _normalizarEmail($email) {
    $email = trim($email);
    $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);
    return strtolower($email);
}

/**
 * Configura la conexión BD para búsquedas case-insensitive
 */
function _configurarConexionBD($mysqli) {
    if (function_exists('configurarConexionBD')) {
        configurarConexionBD($mysqli);
    } else {
        $mysqli->set_charset("utf8mb4");
        $mysqli->query("SET collation_connection = 'utf8mb4_unicode_ci'");
        $mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}

/**
 * Busca usuario por email con múltiples estrategias (fallback)
 */
function _buscarUsuarioPorEmailConFallback($mysqli, $email, $campos = '*') {
    // Implementar lógica de búsqueda con múltiples intentos
    // ...
}
```

**Prioridad:** Media

---

### 2. Cacheo de Resultados de Configuración

**Archivos afectados:**
- `producto_queries.php` - función `obtenerProductosFiltradosCatalogo()` (línea 1281-1295)
- Múltiples funciones que llaman a `obtenerTallesEstandar()`

**Problema:**
`obtenerTallesEstandar()` se llama múltiples veces en la misma petición, pero los resultados no se cachean.

**Mejora propuesta:**
```php
// Al inicio de la función o en un scope compartido
static $talles_estandar_cache = null;
if ($talles_estandar_cache === null) {
    $talles_estandar_cache = obtenerTallesEstandar();
}
```

**Prioridad:** Baja

---

### 3. Validaciones Repetidas

**Archivos afectados:**
- `stock_queries.php` - funciones `validarAjusteStock()` y `validarStockDisponibleVenta()`
- `pago_queries.php` - funciones `actualizarEstadoPago()` y `actualizarPagoCompleto()`

**Problema:**
Validaciones similares se repiten en múltiples funciones (verificación de variante activa, producto activo, etc.).

**Mejora propuesta:**
Crear función auxiliar reutilizable:
```php
/**
 * Obtiene información de validación de una variante (stock, estado activo)
 */
function _obtenerInfoValidacionVariante($mysqli, $id_variante, $usar_for_update = false) {
    $sql = "
        SELECT sv.stock, sv.activo as variante_activa, p.activo as producto_activo 
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = ?
    ";
    if ($usar_for_update) {
        $sql .= " FOR UPDATE";
    }
    // ... implementación
}
```

**Prioridad:** Baja-Media

---

## 📊 Índices Recomendados

### Índices Compuestos para Optimización

```sql
-- Optimizar búsquedas de stock por producto
CREATE INDEX idx_stock_variantes_producto_activo_stock 
ON Stock_Variantes(id_producto, activo, stock, talle, color);

-- Optimizar cálculos de totales en pedidos
CREATE INDEX idx_detalle_pedido_calculo_total 
ON Detalle_Pedido(id_pedido, cantidad, precio_unitario);

-- Optimizar búsquedas de fotos por producto
CREATE INDEX idx_fotos_producto_producto_activo 
ON Fotos_Producto(id_producto, activo, color);

-- Optimizar búsquedas de productos por categoría y activo
CREATE INDEX idx_productos_categoria_activo 
ON Productos(id_categoria, activo, genero);

-- Índice único parcial para pagos aprobados (MySQL 8.0+)
CREATE UNIQUE INDEX idx_pagos_pedido_aprobado 
ON Pagos(id_pedido, estado_pago) 
WHERE estado_pago = 'aprobado';
```

**Nota:** Verificar compatibilidad con versión de MySQL/MariaDB antes de crear índices parciales.

---

## 📝 Resumen de Prioridades

### Alta Prioridad
1. ✅ Validación de estado de pedido en descuento de stock
2. ✅ Optimización de subconsultas múltiples en productos
3. ✅ Crear índices compuestos faltantes

### Media Prioridad
1. ✅ Normalización de emails en BD
2. ✅ Factorizar código duplicado de búsqueda de email
3. ✅ Optimizar uso de FOR UPDATE

### Baja Prioridad
1. ✅ Cacheo de resultados de configuración
2. ✅ Mejorar LIMIT dinámico (ya está validado)
3. ✅ Crear funciones auxiliares para validaciones repetidas

---

## 🔄 Próximos Pasos

1. **Implementar mejoras de alta prioridad:**
   - Crear índices compuestos recomendados
   - Optimizar subconsultas en `_construirQueryDatosCompletos()`
   - Agregar validación de estado de pedido

2. **Refactorizar código duplicado:**
   - Crear funciones auxiliares para normalización de email
   - Extraer validaciones comunes de stock

3. **Monitorear rendimiento:**
   - Usar `EXPLAIN` para verificar uso de índices
   - Medir tiempos de ejecución antes y después de cambios

---

## ✅ Mejoras Implementadas (2024)

### Normalización de Queries en carrito.php
- **Cambio:** Eliminado `carrito_queries.php` (deshabilitado, no se usaba)
- **Cambio:** Normalizadas queries en `carrito.php`:
  - Reemplazado PDO por mysqli para consistencia
  - Creadas funciones `obtenerProductoParaCarrito()` y `obtenerProductosParaCarrito()` en `producto_queries.php`
  - Optimizado: Una sola query para todos los productos del carrito en lugar de múltiples queries (N+1 problem resuelto)
- **Rendimiento:** Mejora significativa - de N queries a 1 query para carritos con múltiples productos
- **Seguridad:** Todas las queries ahora usan prepared statements de mysqli

---

---

## 📁 Archivos de Includes Revisados

### Archivos de Queries (9 archivos)
- ✅ `producto_queries.php` - 2335 líneas, múltiples funciones con subconsultas anidadas
- ✅ `pedido_queries.php` - 704 líneas, queries con cálculos de totales
- ✅ `pago_queries.php` - 705 líneas, validaciones con FOR UPDATE
- ✅ `stock_queries.php` - 834 líneas, gestión de movimientos de stock
- ✅ `usuario_queries.php` - 705 líneas, búsquedas de email con múltiples intentos
- ✅ `perfil_queries.php` - 308 líneas, queries de perfil de usuario
- ✅ `categoria_queries.php` - 87 líneas, queries simples y optimizadas
- ✅ `forma_pago_queries.php` - 98 líneas, queries simples y optimizadas
- ⚠️ `carrito_queries.php` - Deshabilitado (no se usa, carrito usa $_SESSION)

### Archivos de Includes Revisados (Funciones Auxiliares)
- ✅ `auth_check.php` - Sistema de autenticación y roles (sin queries directas)
- ✅ `security_functions.php` - Funciones de seguridad (sin queries directas)
- ✅ `password_functions.php` - Funciones de hash de contraseñas (usa mysqli->query() para SET)
- ✅ `perfil_functions.php` - Funciones helper para perfil (usa queries de perfil_queries.php)

### Observaciones Generales

**Seguridad:**
- ✅ Todas las queries usan prepared statements correctamente
- ✅ Validación de parámetros de entrada presente en la mayoría de funciones
- ⚠️ LIMIT dinámico validado pero podría mejorarse con placeholder
- ⚠️ Uso de mysqli->query() solo para comandos SET (seguro pero podría centralizarse)

**Rendimiento:**
- ⚠️ Subconsultas anidadas en producto_queries.php podrían optimizarse con JOINs
- ✅ Estrategia de dos queries (IDs primero, datos después) en obtenerProductosFiltradosCatalogo()
- ⚠️ Múltiples búsquedas de email (3 intentos) podrían reducirse con normalización en BD
- ⚠️ Cálculos de totales en pedidos podrían usar campo `total` cuando esté disponible

**Abreviaciones:**
- ⚠️ Código duplicado en búsqueda de email entre usuario_queries.php
- ⚠️ Cacheo de obtenerTallesEstandar() podría implementarse
- ⚠️ Validaciones repetidas en stock y pago podrían extraerse a funciones auxiliares

---

**Nota:** Todas las mejoras están documentadas con comentarios en los archivos correspondientes. Este documento sirve como referencia centralizada.

