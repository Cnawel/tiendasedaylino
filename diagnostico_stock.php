<?php
/**
 * Script de diagnóstico para investigar problemas de stock
 * Muestra toda la información relevante para una variante específica
 */

require_once __DIR__ . '/config/database.php';

$id_variante = 169; // Cambiar según la variante problemática

echo "=== DIAGNÓSTICO DE STOCK - Variante #{$id_variante} ===\n\n";

// 1. Stock actual en Stock_Variantes
$sql_stock = "SELECT * FROM Stock_Variantes WHERE id_variante = ?";
$stmt = $mysqli->prepare($sql_stock);
$stmt->bind_param('i', $id_variante);
$stmt->execute();
$stock_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "📦 STOCK EN BASE DE DATOS:\n";
echo "   Stock actual: " . ($stock_data['stock'] ?? 'N/A') . "\n";
echo "   Producto ID: " . ($stock_data['id_producto'] ?? 'N/A') . "\n";
echo "   Talle: " . ($stock_data['talle'] ?? 'N/A') . "\n";
echo "   Color: " . ($stock_data['color'] ?? 'N/A') . "\n";
echo "   Activo: " . ($stock_data['activo'] ?? 'N/A') . "\n\n";

// 2. Reservas activas (pedidos pendientes < 24 horas)
$fecha_limite = date('Y-m-d H:i:s', strtotime('-24 hours'));
$sql_reservas = "
    SELECT
        ms.id_movimiento,
        ms.id_pedido,
        ms.cantidad,
        ms.observaciones,
        ms.fecha_movimiento,
        p.estado_pedido,
        p.fecha_pedido,
        TIMESTAMPDIFF(HOUR, p.fecha_pedido, NOW()) as horas_desde_pedido,
        pag.estado_pago
    FROM Movimientos_Stock ms
    INNER JOIN Pedidos p ON ms.id_pedido = p.id_pedido
    LEFT JOIN Pagos pag ON p.id_pedido = pag.id_pedido
    WHERE ms.id_variante = ?
      AND ms.tipo_movimiento = 'venta'
      AND ms.observaciones LIKE 'RESERVA: %'
      AND p.estado_pedido IN ('pendiente', 'pendiente_validado_stock')
      AND p.fecha_pedido > ?
    ORDER BY p.fecha_pedido DESC
";

$stmt = $mysqli->prepare($sql_reservas);
$stmt->bind_param('is', $id_variante, $fecha_limite);
$stmt->execute();
$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "🔒 RESERVAS ACTIVAS (< 24 horas, pedidos pendientes):\n";
if (empty($reservas)) {
    echo "   ✅ No hay reservas activas\n\n";
} else {
    $total_reservado = 0;
    foreach ($reservas as $reserva) {
        $cantidad = intval($reserva['cantidad']);
        $total_reservado += $cantidad;
        echo "   • Pedido #{$reserva['id_pedido']}: {$cantidad} unidades\n";
        echo "     - Estado pedido: {$reserva['estado_pedido']}\n";
        echo "     - Estado pago: " . ($reserva['estado_pago'] ?? 'sin pago') . "\n";
        echo "     - Horas desde pedido: {$reserva['horas_desde_pedido']}\n";
        echo "     - Fecha: {$reserva['fecha_pedido']}\n\n";
    }
    echo "   📊 TOTAL RESERVADO: {$total_reservado} unidades\n\n";
}

// 3. Cálculo final
$stock_actual = intval($stock_data['stock'] ?? 0);
$stock_reservado = 0;
foreach ($reservas as $reserva) {
    $id_ped = intval($reserva['id_pedido']);
    $cantidad_res = intval($reserva['cantidad']);
    $estado_pago = $reserva['estado_pago'] ?? null;

    // Contar solo si no tiene pago o pago está pendiente
    if (!$estado_pago || in_array($estado_pago, ['pendiente', 'pendiente_aprobacion'])) {
        $stock_reservado += $cantidad_res;
    }
}

$stock_disponible = $stock_actual - $stock_reservado;

echo "📊 CÁLCULO FINAL:\n";
echo "   Stock físico: {$stock_actual}\n";
echo "   Stock reservado: {$stock_reservado}\n";
echo "   Stock disponible: {$stock_disponible}\n\n";

if ($stock_disponible < 0) {
    echo "⚠️ PROBLEMA: El stock disponible es negativo!\n";
} elseif ($stock_disponible == 0 && $stock_actual > 0) {
    echo "⚠️ PROBLEMA: Hay stock físico pero todo está reservado!\n";
} else {
    echo "✅ El cálculo parece correcto.\n";
}

// 4. Todos los movimientos recientes de esta variante
echo "\n📜 ÚLTIMOS 10 MOVIMIENTOS DE STOCK:\n";
$sql_movimientos = "
    SELECT
        ms.*,
        p.estado_pedido
    FROM Movimientos_Stock ms
    LEFT JOIN Pedidos p ON ms.id_pedido = p.id_pedido
    WHERE ms.id_variante = ?
    ORDER BY ms.fecha_movimiento DESC
    LIMIT 10
";
$stmt = $mysqli->prepare($sql_movimientos);
$stmt->bind_param('i', $id_variante);
$stmt->execute();
$movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($movimientos as $mov) {
    $signo = $mov['tipo_movimiento'] === 'venta' ? '-' : '+';
    echo "   {$mov['fecha_movimiento']} | {$signo}{$mov['cantidad']} | {$mov['tipo_movimiento']} | Pedido #{$mov['id_pedido']} | {$mov['observaciones']}\n";
}

$mysqli->close();

echo "\n=== FIN DIAGNÓSTICO ===\n";
?>
