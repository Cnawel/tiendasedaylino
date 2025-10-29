<?php
/**
 * ========================================================================
 * PROCESAR PEDIDO - Tienda Seda y Lino
 * ========================================================================
 * Procesa y guarda el pedido en la base de datos
 * - Valida que el carrito tenga productos
 * - Verifica stock disponible para cada producto/talle/color
 * - Crea el pedido usando transacciones MySQLi
 * - Registra cada producto en Detalle_Pedidos
 * - Actualiza stock en Stock_Variantes
 * - Registra el método de pago seleccionado
 * - Envía email de confirmación al cliente
 * - Limpia el carrito y redirige a confirmación
 * 
 * Funciones principales:
 * - Validación de stock antes de crear pedido
 * - Transacción para garantizar integridad (si algo falla, rollback)
 * - Actualización de stock después de confirmar pedido
 * 
 * Variables principales:
 * - $id_usuario: Usuario que realiza el pedido
 * - $carrito: Productos a procesar
 * - $total: Total del pedido
 * - $forma_pago: Método de pago seleccionado
 * 
 * Tablas utilizadas: Pedidos, Detalle_Pedidos, Stock_Variantes, Formas_Pago
 * ========================================================================
 */

session_start();

require_once 'config/database.php';
require_once 'includes/email_functions.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['mensaje_error'] = "Sesión expirada. Por favor, inicia sesión nuevamente.";
    header('Location: login.php');
    exit;
}

// Verificar que el carrito tenga productos
if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    $_SESSION['mensaje_error'] = "Tu carrito está vacío";
    header('Location: carrito.php');
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

/**
 * Validar y sanitizar datos del formulario
 */
$id_usuario = $_SESSION['id_usuario'];
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$localidad = trim($_POST['localidad'] ?? '');
$provincia = trim($_POST['provincia'] ?? '');
$codigo_postal = trim($_POST['codigo_postal'] ?? '');
$id_forma_pago = (int)($_POST['id_forma_pago'] ?? 0);

// Validar campos requeridos
if (empty($telefono) || empty($direccion) || empty($localidad) || empty($provincia) || empty($codigo_postal) || $id_forma_pago <= 0) {
    $_SESSION['mensaje_error'] = "Todos los campos son obligatorios";
    header('Location: checkout.php');
    exit;
}

/**
 * Iniciar transacción para garantizar integridad de datos
 */
try {
    $mysqli->begin_transaction();
    
    /**
     * Paso 1: Verificar stock y preparar datos del pedido
     */
    $productos_pedido = array();
    $total_pedido = 0;
    
    foreach ($_SESSION['carrito'] as $clave => $item) {
        // Obtener datos del producto y variante
        $sql_producto = "
            SELECT 
                p.id_producto,
                p.nombre_producto,
                p.precio_actual,
                sv.id_variante,
                sv.stock,
                sv.talle,
                sv.color
            FROM Productos p
            INNER JOIN Stock_Variantes sv ON p.id_producto = sv.id_producto
            WHERE p.id_producto = ? 
                AND sv.talle = ? 
                AND sv.color = ?
            FOR UPDATE
            LIMIT 1
        ";
        
        $stmt_producto = $mysqli->prepare($sql_producto);
        $stmt_producto->bind_param('iss', $item['id_producto'], $item['talla'], $item['color']);
        $stmt_producto->execute();
        $result_producto = $stmt_producto->get_result();
        $producto = $result_producto->fetch_assoc();
        
        // Verificar que el producto existe
        if (!$producto) {
            throw new Exception("Producto no encontrado: {$item['id_producto']}");
        }
        
        // Verificar stock disponible
        if ($producto['stock'] < $item['cantidad']) {
            throw new Exception("Stock insuficiente para {$producto['nombre_producto']} (Talla: {$producto['talle']}, Color: {$producto['color']}). Disponible: {$producto['stock']}, Solicitado: {$item['cantidad']}");
        }
        
        // Calcular subtotal
        $subtotal = $producto['precio_actual'] * $item['cantidad'];
        $total_pedido += $subtotal;
        
        // Agregar a lista de productos del pedido
        $productos_pedido[] = array(
            'id_variante' => $producto['id_variante'],
            'cantidad' => $item['cantidad'],
            'precio_unitario' => $producto['precio_actual'],
            'subtotal' => $subtotal,
            'nombre_producto' => $producto['nombre_producto'],
            'talle' => $producto['talle'],
            'color' => $producto['color']
        );
    }
    
    /**
     * Paso 2: Crear el pedido en la tabla Pedidos
     */
    $sql_pedido = "
        INSERT INTO Pedidos (id_usuario, fecha_pedido, estado_pedido)
        VALUES (?, NOW(), 'pendiente')
    ";
    
    $stmt_pedido = $mysqli->prepare($sql_pedido);
    $stmt_pedido->bind_param('i', $id_usuario);
    $stmt_pedido->execute();
    
    // Obtener ID del pedido creado
    $id_pedido = $mysqli->insert_id;
    
    /**
     * Paso 3: Insertar detalles del pedido y actualizar stock
     */
    foreach ($productos_pedido as $producto) {
        // Insertar detalle del pedido
        $sql_detalle = "
            INSERT INTO Detalle_Pedido (id_pedido, id_variante, cantidad, precio_unitario)
            VALUES (?, ?, ?, ?)
        ";
        
        $stmt_detalle = $mysqli->prepare($sql_detalle);
        $stmt_detalle->bind_param('iidd', $id_pedido, $producto['id_variante'], $producto['cantidad'], $producto['precio_unitario']);
        $stmt_detalle->execute();
        
        // Actualizar stock (restar cantidad vendida)
        $sql_update_stock = "
            UPDATE Stock_Variantes 
            SET stock = stock - ?
            WHERE id_variante = ?
        ";
        
        $stmt_update_stock = $mysqli->prepare($sql_update_stock);
        $stmt_update_stock->bind_param('ii', $producto['cantidad'], $producto['id_variante']);
        $stmt_update_stock->execute();
        
        // Registrar movimiento de stock
        $cantidad_negativa = -$producto['cantidad']; // Negativo porque es una salida
        $observaciones = "Venta - Pedido #{$id_pedido}";
        
        $sql_movimiento = "
            INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, fecha_movimiento, id_usuario, observaciones)
            VALUES (?, 'venta', ?, NOW(), ?, ?)
        ";
        
        $stmt_movimiento = $mysqli->prepare($sql_movimiento);
        $stmt_movimiento->bind_param('iiis', $producto['id_variante'], $cantidad_negativa, $id_usuario, $observaciones);
        $stmt_movimiento->execute();
    }
    
    /**
     * Paso 4: Crear registro de pago
     */
    $sql_pago = "
        INSERT INTO Pagos (id_pedido, id_forma_pago, estado_pago, fecha_pago)
        VALUES (?, ?, 'pendiente', NOW())
    ";
    
    $stmt_pago = $mysqli->prepare($sql_pago);
    $stmt_pago->bind_param('ii', $id_pedido, $id_forma_pago);
    $stmt_pago->execute();
    
    /**
     * Paso 5: Actualizar datos de usuario si es necesario
     */
    $sql_update_usuario = "
        UPDATE Usuarios 
        SET telefono = ?,
            direccion = ?,
            localidad = ?,
            provincia = ?,
            codigo_postal = ?
        WHERE id_usuario = ?
    ";
    
    $stmt_update_usuario = $mysqli->prepare($sql_update_usuario);
    $stmt_update_usuario->bind_param('sssssi', $telefono, $direccion, $localidad, $provincia, $codigo_postal, $id_usuario);
    $stmt_update_usuario->execute();
    
    /**
     * Confirmar transacción
     */
    $mysqli->commit();
    
    /**
     * Paso 6: Obtener datos del usuario para el email
     */
    $sql_usuario = "SELECT * FROM Usuarios WHERE id_usuario = ? LIMIT 1";
    $stmt_usuario = $mysqli->prepare($sql_usuario);
    $stmt_usuario->bind_param('i', $id_usuario);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();
    $usuario = $result_usuario->fetch_assoc();
    
    /**
     * Paso 7: Preparar datos para confirmación y email
     */
    $datos_pedido_confirmacion = array(
        'id_pedido' => $id_pedido,
        'total' => $total_pedido,
        'productos' => $productos_pedido,
        'fecha' => date('d/m/Y H:i'),
        'direccion' => $direccion . ', ' . $localidad . ', ' . $provincia . ' (' . $codigo_postal . ')'
    );
    
    /**
     * Paso 8: Enviar email de confirmación al cliente
     */
    $email_enviado = false;
    try {
        $email_enviado = enviar_email_confirmacion_pedido($datos_pedido_confirmacion, $usuario);
        if ($email_enviado) {
            error_log("Email de confirmación enviado exitosamente al pedido #$id_pedido");
        } else {
            error_log("No se pudo enviar email de confirmación al pedido #$id_pedido");
        }
    } catch (Exception $e) {
        error_log("Error al enviar email de confirmación: " . $e->getMessage());
    }
    
    /**
     * Paso 9: Limpiar carrito y guardar datos para confirmación
     */
    $_SESSION['carrito'] = array();
    $_SESSION['pedido_exitoso'] = $datos_pedido_confirmacion;
    $_SESSION['email_enviado'] = $email_enviado;
    
    // Redirigir a página de confirmación
    header('Location: confirmacion-pedido.php');
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $mysqli->rollback();
    
    // Guardar mensaje de error
    $_SESSION['mensaje_error'] = "Error al procesar el pedido: " . $e->getMessage();
    
    // Redirigir de vuelta al checkout
    header('Location: checkout.php');
    exit;
}
?>

