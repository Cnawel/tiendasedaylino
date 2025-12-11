# Reporte: Auditoría de `stock_queries.php`

Fecha: 2025-12-01

Resumen corto
- Archivo auditado: `includes/queries/stock_queries.php`
- Objetivo: identificar funciones sin uso, helpers internos, funciones deprecadas y duplicados o lógica repetida que convenga documentar o consolidar.

Índice rápido
- Funciones públicas principales
- Helpers / funciones internas
- Funciones marcadas como `@deprecated`
- Uso por archivo (callers principales)
- Recomendaciones y siguiente pasos

Funciones públicas principales (API del módulo)
- `registrarMovimientoStock($mysqli, $id_variante, $tipo_movimiento, $cantidad, ...)`
  - Uso: `marketing-confirmar-csv.php`, `marketing-editar-producto.php`, `procesar-pedido.php`, `includes/queries/pedido_queries.php`, `includes/queries/pago_queries.php`.
  - Observación: función central para trazabilidad y actualización de stock. Mantener y documentar como API.

- `descontarStockPedido($mysqli, $id_pedido, ...)`
  - Uso: `includes/queries/pago_queries.php` (aprobar pago / flujo de pagos).
  - Observación: idempotente, crítico para proceso de pago.

- `revertirStockPedido($mysqli, $id_pedido, ...)`
  - Uso: `includes/queries/pedido_queries.php`, `includes/queries/pago_queries.php`.
  - Observación: idempotente; mantener.

- `validarStockDisponible($mysqli, $id_producto, $talle, $color, ...)`
  - Uso: `carrito.php`, `carrito_functions.php`, `detalle-producto.php`, `procesar-pedido.php`.
  - Observación: reemplaza funciones antiguas de validación y es la recomendada para nuevos usos.

- `insertarVarianteStock(...)`, `obtenerVariantePorId(...)`, `actualizarVarianteStock(...)`, `desactivarVarianteStock(...)`
  - Uso: `marketing-editar-producto.php`, `marketing-confirmar-csv.php`.
  - Observación: operaciones CRUD de variantes; mantener con la documentación adecuada.

- `obtenerMovimientosStockRecientes($mysqli, $limite)`
  - Uso: `ventas.php`, `marketing.php`.

Helpers / funciones internas (uso local dentro del módulo)
- `_obtenerDatosVarianteYProducto($mysqli, $id_variante)`
  - Uso: internamente por `validarAjusteStock()` y `validarStockDisponibleVenta()`.
  - Observación: prefijo `_` apropiado; marcar `@internal` en PHPDoc.

- `actualizarStockDesdeMovimiento($mysqli, $id_variante, $tipo_movimiento, $cantidad)`
  - Uso: interno por `registrarMovimientoStock()`.
  - Observación: función crítica para aplicar cambios con validación atómica (UPDATE ... WHERE stock >= ?). Mantener interna.

- `validarRangoStock($stock)` y `validarRangoCantidadMovimiento($cantidad)`
  - Uso: validaciones internas en el mismo archivo.

- `verificarCoherenciaStockDespuesDescuento($mysqli, $id_pedido, $stock_antes)`
  - Uso: sanity-check solo en modo debug, interno.

- `obtenerStockReservado($mysqli, $id_variante)` y `liberarReservasExpiradas($mysqli)`
  - Uso: `validarStockDisponible()` (liberar reservas en modo 'definitivo') y llamadas internas en el mismo módulo.
  - Observación: `liberarReservasExpiradas()` no aparece llamada desde fuera del archivo salvo indirectamente por `validarStockDisponible()`; mantener pero documentar su invocación (cron vs llamada ad-hoc).

Funciones marcadas como deprecated (documentadas en el archivo)
- `validarStockDisponibleVenta($mysqli, $id_variante, $cantidad)`
  - Comentario en código: deprecated, sustituir por `validarStockDisponible()`.
  - Recomendación: añadir `@deprecated` en PHPDoc y opcionalmente un `error_log` o `trigger_error` para detectar llamadas en runtime.

- `validarStockDisponibleCarrito(...)` (deprecated)
  - Delegaba a `validarStockDisponible(..., 'preliminar', ...)`.

Uso por archivo — callers principales (resumen)
- `procesar-pedido.php` — incluye `stock_queries.php` y usa `reservarStockPedido()` y `validarStockDisponible(...)`.
- `includes/queries/pago_queries.php` — llama a `descontarStockPedido()` y `revertirStockPedido()` durante flujos de pago.
- `includes/queries/pedido_queries.php` — llama a `revertirStockPedido()` y usa `registrarMovimientoStock()` en operaciones de cancelación/devolución.
- `marketing-confirmar-csv.php` / `marketing-editar-producto.php` — usan `insertarVarianteStock()`, `registrarMovimientoStock()`, `verificarVarianteExistente*` y otros helpers.
- `carrito.php` / `carrito_functions.php` / `detalle-producto.php` — usan `validarStockDisponible()` y `obtenerStockReservado()` para UX y validaciones preliminares.
- `ventas.php` / `marketing.php` — usan `obtenerMovimientosStockRecientes()` para dashboards.

Posibles duplicaciones / áreas de consolidación
- Múltiples archivos consultan `Detalle_Pedido` y realizan agregaciones similares (ej.: totales por pedido). Hay código parecido en `pedido_queries.php`, `pago_queries.php`, `producto_queries.php` y `stock_queries.php`.
  - Recomendación: crear utilidades compartidas si se busca centralizar consultas (ej.: `includes/queries/detalle_pedido_queries.php`) pero hacerlo con cuidado por la necesidad de `FOR UPDATE` en algunas rutas.

- Verificaciones de existencia de variante: existen dos funciones muy similares:
  - `verificarVarianteExistente(...)` (por nombre_producto + id_categoria + genero + talle + color)
  - `verificarVarianteExistentePorProducto(...)` (por `id_producto`, talle, color)
  - Recomendación: conservar ambas si se usan en contextos distintos (uno busca colisiones dentro del mismo producto, otro en todo el grupo de productos con mismo nombre/categoria/genero). Si no se usan ambas, consolidar.

Recomendaciones operativas (mínimas, seguras)
1. Añadir PHPDoc `@internal` a helpers que son solo para uso interno (`_obtenerDatosVarianteYProducto`, `actualizarStockDesdeMovimiento`, etc.).
2. Marcar `validarStockDisponibleVenta` y `validarStockDisponibleCarrito` con `@deprecated` y añadir una nota de reemplazo en el PHPDoc apuntando a `validarStockDisponible`.
3. Crear el archivo de reporte (este documento) en repo para que el equipo lo revise antes de eliminar o refactorizar código crítico de stock.
4. Antes de eliminar cualquier función (ej: supuesta sin uso), ejecutar un pase en staging y pruebas de pago/pedido completas, ya que estas funciones están en la ruta del flujo crítico de stock.

Próximos pasos propuestos (puedo ejecutar ahora)
- [x] 1) Crear este reporte en el repo (se completó).
- [ ] 2) Aplicar parches no invasivos: añadir `@deprecated`/`@internal` en `includes/queries/stock_queries.php` (puedo prepararlos y abrir PR localmente si lo autorizas).
- [ ] 3) Hacer un barrido para detectar funciones públicas definidas pero nunca llamadas desde el workspace (candidato a eliminación).
 - [x] 3) Hacer un barrido para detectar funciones públicas definidas pero nunca llamadas desde el workspace (candidato a eliminación).

## Resultados del barrido de funciones públicas

- **Resumen:** Se buscó en todo el workspace llamadas y referencias a las funciones públicas definidas en `includes/queries/stock_queries.php`.
- **Conclusión:** No se encontraron funciones públicas definidas en `stock_queries.php` que estén completamente sin uso en el workspace. Todas las funciones del API público listadas en este documento tienen al menos una referencia o llamada desde otros archivos o desde documentación/archivos de tests.
- **Detalles adicionales:**
  - **Funciones usadas externamente:** `registrarMovimientoStock`, `descontarStockPedido`, `revertirStockPedido`, `validarStockDisponible`, `insertarVarianteStock`, `obtenerVariantePorId`, `actualizarVarianteStock`, `desactivarVarianteStock`, `obtenerMovimientosStockRecientes`, entre otras — todas aparecen en callers reales (`includes/queries/pago_queries.php`, `includes/queries/pedido_queries.php`, `marketing-*`, `carrito.php`, `ventas.php`, `perfil.php`, tests, etc.).
  - **Funciones con uso sólo interno/documentación:** Algunas utilidades y helpers (ej. `validarRangoStock`, `validarRangoCantidadMovimiento`, `actualizarStockDesdeMovimiento`, `validarAjusteStock`, `verificarCoherenciaStockDespuesDescuento`) se usan únicamente dentro de `stock_queries.php` o se mencionan solamente en documentación o tests. Estas funciones se consideran **internas** y se recomienda añadir `@internal` en su PHPDoc.
  - **Funciones que aparecen sólo por documentación o llamadas indirectas:** `liberarReservasExpiradas` se invoca internamente desde `validarStockDisponible()` y no aparece llamadas directamente desde otros módulos salvo documentación; mantenerla (por cron o invocación indirecta) y documentar su uso.

- **Recomendación inmediata:** No eliminar ninguna función pública ahora. Añadir PHPDoc `@internal` a las helpers internas y, opcionalmente, marcar `@deprecated` las funciones ya identificadas como legacy (p. ej. `validarStockDisponibleVenta`, `validarStockDisponibleCarrito`). Ejecutar pruebas de integración en staging antes de eliminar funciones.

- [ ] 4) Preparar parches para consolidación de queries repetidas (requiere pruebas manuales).

Si quieres que continúe, indica si prefieres que (A) aplique parches de documentación no invasivos ahora, o (B) que haga un barrido adicional por funciones públicas sin uso.
