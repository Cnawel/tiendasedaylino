<?php 
/**
 * ========================================================================
 * LISTADO DE TABLAS SQL - Tienda Seda y Lino
 * ========================================================================
 * Muestra todas las tablas disponibles en la base de datos
 * Permite acceder a cada tabla para visualizar y gestionar sus datos
 * ========================================================================
 */
session_start();

// Cargar sistema de autenticación centralizado
require_once __DIR__ . '/../includes/auth_check.php';

// Verificar que el usuario esté logueado y sea admin
requireAdmin();

// Conectar a la base de datos
require_once __DIR__ . '/../config/database.php';

// Obtener los nombres de las tablas que hay en nuestra base de datos
$tablas = $mysqli->query("SHOW TABLES");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablas SQL</title>
</head>
<body>

    <h1>Tablas de la base: tiendasedaylino_db</h1>

    <a href="../admin.php">
        <button>← Volver al Panel de Administración</button>
    </a>
    <br><br>

    <?php 
       //recorremos el array que contiene los nombres de nuestras tablas
       while($fila = $tablas->fetch_array()){
          //guardamos el nombre de las tablas en una variable
          $tabla = $fila[0];
          // Generamos un botón para cada una de nuestras tablas
          // Escapamos el nombre de la tabla para seguridad
          $tabla_escaped = htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8');
          echo "<a href='mostrar_tabla.php?tabla=" . urlencode($tabla) . "'>
               <button>$tabla_escaped</button>
          </a>";
       }
    ?>
    
</body>
</html>