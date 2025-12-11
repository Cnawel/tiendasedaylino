# Histórico de Modificaciones - includes/queries

Este archivo registra todas las modificaciones realizadas en los archivos de queries según las reglas del proyecto.

## Formato
Fecha-Hora>Archivo>"modificacion realizada"

---

## 2025-11-04 23:37:57>usuario_queries.php>"Agregadas 6 nuevas funciones: crearUsuarioCliente, obtenerUsuarioPorEmailRecupero, obtenerTextoPreguntaRecupero, obtenerPreguntaRecuperoUsuario, verificarHashContrasena, y movida función buscarUsuarioPorEmail desde password_functions.php"

## 2025-11-04 23:37:57>pedido_queries.php>"Agregada función verificarDetallePedidoUsuario para verificar que un detalle de pedido pertenece a un usuario"

## 2025-11-04 23:37:57>register.php>"Refactorizado: reemplazadas queries inline por funciones de queries (obtenerPreguntasRecupero, verificarPreguntaRecupero, verificarEmailExistente, crearUsuarioCliente, verificarHashContrasena, desactivarUsuario)"

## 2025-11-04 23:37:57>recuperar-contrasena.php>"Refactorizado: reemplazadas queries inline por funciones de queries (obtenerPreguntasRecupero, verificarPreguntaRecupero, obtenerUsuarioPorEmailRecupero, actualizarContrasena, obtenerPreguntaRecuperoUsuario, obtenerTextoPreguntaRecupero)"

## 2025-11-04 23:37:57>perfil.php>"Refactorizado: reemplazada query inline por función verificarDetallePedidoUsuario de pedido_queries.php"

## 2025-11-04 23:37:57>login.php>"Actualizado para usar buscarUsuarioPorEmail de usuario_queries.php en lugar de password_functions.php"

## 2025-11-04 23:37:57>password_functions.php>"Removida función buscarUsuarioPorEmail (movida a usuario_queries.php)"

## 2025-11-04 23:37:57>perfil.php>"Eliminados requires duplicados: pedido_queries.php y stock_queries.php (movidos al inicio del archivo, eliminados de bloques condicionales)"

## 2025-11-04 23:37:57>usuario_queries.php>"Actualizada función obtenerHashContrasena para filtrar por activo = 1 (seguridad, ahora coincide con perfil_queries.php)"

## NOTA SOBRE DUPLICADOS:
- Función obtenerHashContrasena existe en usuario_queries.php y perfil_queries.php con funcionalidad idéntica (ambas filtran por activo = 1)
- Se mantienen ambas porque cada archivo se incluye en contextos diferentes (admin.php usa usuario_queries, perfil.php usa perfil_queries)
- Código de configuración de conexión duplicado en obtenerUsuarioPorEmailRecupero y buscarUsuarioPorEmail (se mantiene por regla "Lo que ANDA NO SE EDITA")

---

