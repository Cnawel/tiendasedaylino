<?php
/**
 * Carrito de compras con $_SESSION
 * Proyecto: Tienda Seda y Lino
 * Descripción: Este módulo gestiona el carrito en memoria de sesión,
 * sin necesidad de tablas adicionales en la base de datos.
 */

// Iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = []; // Carrito vacío al comenzar
}

/**
 * Agrega un producto al carrito.
 * 
 * @param int $id_producto   Identificador único del producto
 * @param int $cantidad      Cantidad a agregar
 * @param float $precio      Precio unitario vigente en el momento de la compra
 */
function agregarAlCarrito($id_producto, $cantidad, $precio) {
    // Recorremos el carrito para ver si el producto ya existe
    foreach ($_SESSION['carrito'] as &$item) {
        if ($item['id_producto'] == $id_producto) {
            // Si ya existe, acumulamos la cantidad
            $item['cantidad'] += $cantidad;
            return; // Salimos de la función (no agregamos nuevo ítem)
        }
    }
    // Si no existe, lo agregamos como un nuevo registro
    $_SESSION['carrito'][] = [
        'id_producto' => $id_producto,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio
    ];
}

/**
 * Elimina un producto del carrito según su ID.
 * 
 * @param int $id_producto   Identificador único del producto
 */
function eliminarDelCarrito($id_producto) {
    foreach ($_SESSION['carrito'] as $index => $item) {
        if ($item['id_producto'] == $id_producto) {
            unset($_SESSION['carrito'][$index]); // Quitamos el producto
        }
    }
    // Reindexamos el array para evitar "huecos"
    $_SESSION['carrito'] = array_values($_SESSION['carrito']);
}

/**
 * Muestra el contenido actual del carrito con subtotales y total.
 * 
 * @return void
 */
function mostrarCarrito() {
    $total = 0;
    echo "<h3>Carrito de compras</h3>";
    echo "<ul>";
    foreach ($_SESSION['carrito'] as $item) {
        // Calcular subtotal por cada ítem
        $subtotal = $item['cantidad'] * $item['precio_unitario'];
        $total += $subtotal;

        echo "<li>
                Producto {$item['id_producto']} - 
                Cantidad: {$item['cantidad']} - 
                Precio unitario: {$item['precio_unitario']} - 
                Subtotal: $subtotal
              </li>";
    }
    echo "</ul>";
    echo "<strong>Total: $total</strong>";
}

/**
 * Vacía completamente el carrito.
 * 
 * @return void
 */
function vaciarCarrito() {
    $_SESSION['carrito'] = [];
}

// ---------------- DEMO DE FUNCIONAMIENTO (comentado para producción) ----------------
/*
// Agregamos productos al carrito (simulación)
agregarAlCarrito(101, 2, 4500);  // Producto ID 101 (ej: Blusa)
agregarAlCarrito(202, 1, 7500);  // Producto ID 202 (ej: Pantalón)

// Mostramos carrito
mostrarCarrito();

// Eliminamos un producto
eliminarDelCarrito(101);

// Mostramos carrito nuevamente
echo "<hr>";
mostrarCarrito();
*/
