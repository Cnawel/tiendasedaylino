# Consultas SQL Centralizadas

## Estructura

Este directorio contiene todas las consultas SQL organizadas por funcionalidad. Cada archivo agrupa consultas relacionadas a una entidad o módulo específico.

```
includes/queries/
├── README.md              (este archivo)
├── producto_queries.php   (consultas de productos - incluye funciones para carrito)
├── pedido_queries.php     (consultas de pedidos)
├── pago_queries.php       (consultas de pagos)
├── stock_queries.php      (consultas de stock)
├── usuario_queries.php    (consultas de usuarios)
├── perfil_queries.php     (consultas de perfil)
├── categoria_queries.php  (consultas de categorías)
└── forma_pago_queries.php (consultas de formas de pago)
```

**Nota:** `carrito_queries.php` fue eliminado porque el carrito usa `$_SESSION['carrito']` en lugar de base de datos. Las queries de productos para el carrito están en `producto_queries.php` (funciones `obtenerProductoParaCarrito()` y `obtenerProductosParaCarrito()`).

## Uso

### 1. Incluir el archivo de consultas

```php
require_once __DIR__ . '/includes/queries/producto_queries.php';
```

### 2. Llamar a las funciones

```php
// Ejemplo: Obtener un producto por ID
$producto = obtenerProductoPorId($mysqli, $id_producto);

// Ejemplo: Obtener variantes de stock
$variantes = obtenerVariantesStock($mysqli, $id_producto);

// Ejemplo: Obtener productos relacionados
$relacionados = obtenerProductosRelacionados($mysqli, $categoria_id, $producto_id, 3);
```

## Ventajas

- ✅ **Reutilización**: Una consulta se define una vez y se usa en múltiples páginas
- ✅ **Mantenimiento**: Cambios en SQL se hacen en un solo lugar
- ✅ **Organización**: Código más limpio y fácil de encontrar
- ✅ **Seguridad**: Todas las consultas usan prepared statements
- ✅ **Documentación**: Cada función tiene comentarios explicativos

## Convenciones

1. **Nombres de funciones**: `obtener[NombreEntidad]Por[Filtro]()`
2. **Parámetros**: Siempre incluir `$mysqli` como primer parámetro
3. **Retorno**: Arrays asociativos para resultados, `null` o `[]` si no hay datos
4. **Prepared statements**: Siempre usar para prevenir SQL injection

## Ejemplo de creación de nuevo archivo

```php
<?php
/**
 * ========================================================================
 * CONSULTAS SQL DE CARRITO - Tienda Seda y Lino
 * ========================================================================
 */

/**
 * Obtiene los items del carrito de un usuario
 * 
 * @param mysqli $mysqli Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return array Array de items del carrito
 */
function obtenerItemsCarrito($mysqli, $id_usuario) {
    $sql = "
        SELECT c.id_item, c.id_producto, c.talle, c.color, c.cantidad,
               p.nombre_producto, p.precio_actual
        FROM Carrito c
        INNER JOIN Productos p ON c.id_producto = p.id_producto
        WHERE c.id_usuario = ?
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    $stmt->close();
    return $items;
}
```

## Archivos existentes

### producto_queries.php
- `obtenerProductoPorId()` - Obtiene un producto por ID
- `obtenerProductoParaCarrito()` - Obtiene datos básicos de un producto para carrito
- `obtenerProductosParaCarrito()` - Obtiene datos de múltiples productos para carrito (optimizada)
- `obtenerVariantesStock()` - Obtiene variantes de stock
- `obtenerFotosProducto()` - Obtiene fotos del producto
- `obtenerProductosRelacionados()` - Obtiene productos relacionados
- `obtenerProductosFiltradosCatalogo()` - Lista productos con filtros para catálogo
- `obtenerProductosMarketing()` - Lista productos para panel de marketing

## Diferencias importantes entre funciones de catálogo y marketing

### `obtenerProductosFiltradosCatalogo()` vs `obtenerProductosMarketing()`

Estas dos funciones tienen propósitos diferentes y aplican filtros distintos:

#### `obtenerProductosFiltradosCatalogo()` - Para catálogo público
- **Propósito**: Mostrar productos disponibles para compra a clientes
- **Filtros aplicados**:
  - ✅ `sv.stock > 0` - Solo productos con stock disponible
  - ✅ `sv.activo = 1` - Solo variantes activas
  - ✅ `p.activo = 1` - Solo productos activos
  - ✅ `c.activo = 1` - Solo categorías activas
- **Resultado**: Muestra productos individuales por variante (cada color como producto separado)
- **Uso**: `catalogo.php` - Vista pública del catálogo

#### `obtenerProductosMarketing()` - Para panel de gestión
- **Propósito**: Gestionar todos los productos activos (incluso sin stock)
- **Filtros aplicados**:
  - ❌ **NO filtra por stock** - Muestra productos sin stock para gestión
  - ✅ `sv.activo = 1` - Solo variantes activas (en queries secundarias)
  - ✅ `p.activo = 1` - Solo productos activos
  - ✅ `c.activo = 1` - Solo categorías activas
- **Resultado**: Muestra productos agrupados por nombre (todos los colores y talles juntos)
- **Uso**: `marketing.php` - Panel de gestión de productos

### ¿Por qué estas diferencias?

1. **Catálogo público**: Los clientes solo deben ver productos que pueden comprar (con stock disponible)
2. **Panel marketing**: Los administradores necesitan gestionar TODOS los productos activos, incluso los que están sin stock, para poder:
   - Ver qué productos necesitan reposición
   - Gestionar productos que están temporalmente agotados
   - Planificar reposiciones de inventario

### Consistencia en filtros de activos

Ambas funciones aplican consistentemente:
- `p.activo = 1` - Solo productos activos
- `sv.activo = 1` - Solo variantes activas
- `c.activo = 1` - Solo categorías activas

La única diferencia intencional es el filtro de stock (`sv.stock > 0`), que solo se aplica en el catálogo público.

