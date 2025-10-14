<?php
/**
 * ========================================================================
 * PROCESAR PEDIDO - Tienda Seda y Lino
 * ========================================================================
 * Procesa la finalización del pedido:
 * - Valida datos del formulario
 * - Verifica stock disponible
 * - Crea el pedido en la base de datos
 * - Registra detalles del pedido
 * - Actualiza stock y registra movimientos
 * - Crea registro de pago
 * - Limpia el carrito
 * 
 * Usa transacciones para garantizar integridad de datos
 * 
 * @author Tienda Seda y Lino
 * @version 1.0
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
    $pdo->beginTransaction();
    
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
            WHERE p.id_producto = :id_producto 
                AND sv.talle = :talle 
                AND sv.color = :color
            FOR UPDATE
        ";
        
        $stmt_producto = $pdo->prepare($sql_producto);
        $stmt_producto->bindParam(':id_producto', $item['id_producto'], PDO::PARAM_INT);
        $stmt_producto->bindParam(':talle', $item['talla'], PDO::PARAM_STR);
        $stmt_producto->bindParam(':color', $item['color'], PDO::PARAM_STR);
        $stmt_producto->execute();
        $producto = $stmt_producto->fetch(PDO::FETCH_ASSOC);
        
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
        VALUES (:id_usuario, NOW(), 'pendiente')
    ";
    
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_pedido->execute();
    
    // Obtener ID del pedido creado
    $id_pedido = $pdo->lastInsertId();
    
    /**
     * Paso 3: Insertar detalles del pedido y actualizar stock
     */
    foreach ($productos_pedido as $producto) {
        // Insertar detalle del pedido
        $sql_detalle = "
            INSERT INTO Detalle_Pedido (id_pedido, id_variante, cantidad, precio_unitario)
            VALUES (:id_pedido, :id_variante, :cantidad, :precio_unitario)
        ";
        
        $stmt_detalle = $pdo->prepare($sql_detalle);
        $stmt_detalle->bindParam(':id_pedido', $id_pedido, PDO::PARAM_INT);
        $stmt_detalle->bindParam(':id_variante', $producto['id_variante'], PDO::PARAM_INT);
        $stmt_detalle->bindParam(':cantidad', $producto['cantidad'], PDO::PARAM_INT);
        $stmt_detalle->bindParam(':precio_unitario', $producto['precio_unitario'], PDO::PARAM_STR);
        $stmt_detalle->execute();
        
        // Actualizar stock (restar cantidad vendida)
        $sql_update_stock = "
            UPDATE Stock_Variantes 
            SET stock = stock - :cantidad
            WHERE id_variante = :id_variante
        ";
        
        $stmt_update_stock = $pdo->prepare($sql_update_stock);
        $stmt_update_stock->bindParam(':cantidad', $producto['cantidad'], PDO::PARAM_INT);
        $stmt_update_stock->bindParam(':id_variante', $producto['id_variante'], PDO::PARAM_INT);
        $stmt_update_stock->execute();
        
        // Registrar movimiento de stock
        $cantidad_negativa = -$producto['cantidad']; // Negativo porque es una salida
        $observaciones = "Venta - Pedido #{$id_pedido}";
        
        $sql_movimiento = "
            INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, fecha_movimiento, id_usuario, observaciones)
            VALUES (:id_variante, 'venta', :cantidad, NOW(), :id_usuario, :observaciones)
        ";
        
        $stmt_movimiento = $pdo->prepare($sql_movimiento);
        $stmt_movimiento->bindParam(':id_variante', $producto['id_variante'], PDO::PARAM_INT);
        $stmt_movimiento->bindParam(':cantidad', $cantidad_negativa, PDO::PARAM_INT);
        $stmt_movimiento->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt_movimiento->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
        $stmt_movimiento->execute();
    }
    
    /**
     * Paso 4: Crear registro de pago
     */
    $sql_pago = "
        INSERT INTO Pagos (id_pedido, id_forma_pago, estado_pago, fecha_pago)
        VALUES (:id_pedido, :id_forma_pago, 'pendiente', NOW())
    ";
    
    $stmt_pago = $pdo->prepare($sql_pago);
    $stmt_pago->bindParam(':id_pedido', $id_pedido, PDO::PARAM_INT);
    $stmt_pago->bindParam(':id_forma_pago', $id_forma_pago, PDO::PARAM_INT);
    $stmt_pago->execute();
    
    /**
     * Paso 5: Actualizar datos de usuario si es necesario
     */
    $sql_update_usuario = "
        UPDATE Usuarios 
        SET telefono = :telefono,
            direccion = :direccion,
            localidad = :localidad,
            provincia = :provincia,
            codigo_postal = :codigo_postal
        WHERE id_usuario = :id_usuario
    ";
    
    $stmt_update_usuario = $pdo->prepare($sql_update_usuario);
    $stmt_update_usuario->bindParam(':telefono', $telefono, PDO::PARAM_STR);
    $stmt_update_usuario->bindParam(':direccion', $direccion, PDO::PARAM_STR);
    $stmt_update_usuario->bindParam(':localidad', $localidad, PDO::PARAM_STR);
    $stmt_update_usuario->bindParam(':provincia', $provincia, PDO::PARAM_STR);
    $stmt_update_usuario->bindParam(':codigo_postal', $codigo_postal, PDO::PARAM_STR);
    $stmt_update_usuario->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_update_usuario->execute();
    
    /**
     * Confirmar transacción
     */
    $pdo->commit();
    
    /**
     * Paso 6: Obtener datos del usuario para el email
     */
    $sql_usuario = "SELECT * FROM Usuarios WHERE id_usuario = :id_usuario";
    $stmt_usuario = $pdo->prepare($sql_usuario);
    $stmt_usuario->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_usuario->execute();
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
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
    $pdo->rollBack();
    
    // Guardar mensaje de error
    $_SESSION['mensaje_error'] = "Error al procesar el pedido: " . $e->getMessage();
    
    // Redirigir de vuelta al checkout
    header('Location: checkout.php');
    exit;
}
?>

