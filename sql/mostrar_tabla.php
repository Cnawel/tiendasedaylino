<?php 
/**
 * ========================================================================
 * VISUALIZACIÓN DE TABLA SQL - Tienda Seda y Lino
 * ========================================================================
 * Muestra todos los registros de una tabla específica
 * Permite editar y eliminar registros individuales
 * ========================================================================
 */
session_start();

// Cargar sistema de autenticación centralizado
require_once __DIR__ . '/../includes/auth_check.php';

// Verificar que el usuario esté logueado y sea admin
requireAdmin();

// Conectar a la base de datos
require_once __DIR__ . '/../config/database.php';

// Obtenemos el nombre de la tabla por el método GET
// Validar y escapar el nombre de la tabla para seguridad
$tabla = isset($_GET['tabla']) ? $_GET['tabla'] : '';
if (empty($tabla)) {
    header('Location: ../db-tablas.php');
    exit;
}
// Validar que el nombre de la tabla solo contenga caracteres permitidos
if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla)) {
    die('Nombre de tabla inválido');
}

   // Obtenemos el nombre de las columnas que tiene nuestra tabla
   // Usamos backticks para escapar el nombre de la tabla (ya validado con regex)
   $tabla_escaped = "`" . $tabla . "`";
   $columnas = $mysqli->query("SHOW COLUMNS FROM $tabla_escaped");
   // Traemos los datos que contiene nuestra tabla
   $filas = $mysqli->query("SELECT * FROM $tabla_escaped");

   // Consultamos el nombre de nuestra clave primaria
   $consulta_pk = $mysqli->query("SHOW KEYS FROM $tabla_escaped WHERE Key_name = 'PRIMARY'");
   $pk_result = $consulta_pk->fetch_assoc();
   if ($pk_result && isset($pk_result['Column_name'])) {
       $pk = $pk_result['Column_name'];
   } else {
       // Si no hay clave primaria, usar la primera columna como identificador
       $pk = $columnas->fetch_assoc()['Field'];
       $columnas->data_seek(0); // Resetear el puntero del resultado
   }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabla: <?php echo $tabla ?></title>
</head>
<body>

    <h1>Tabla: <?php echo $tabla ?></h1>

    <a href="../db-tablas.php">
        <button>← Volver</button>
    </a>

    <table>
        <thead>
            <tr>
                <!-- aca estan las columnas de la tabla de la base (dentro de etiquetas th) -->
                <?php 
                   //recorremos el array e imprimimos el nombre de cada una de las columnas
                   while($col = $columnas->fetch_assoc()){
                       echo "<th>".$col['Field']."</th>";
                   }
                   //columna extra para los botones Editar/Eliminar
                   echo "<th>Acciones</th>";
                ?>
            </tr>
        </thead>

        <tbody>
            <!-- aca estan las filas con los datos de la base (dentro de etiquetas td) -->
            <?php 
               //recorremos el array e imprimimos los datos que contienen las filas
               while($fila = $filas->fetch_assoc()){
                  echo "<tr>";
                
                  //aca imprimimos cada valor de la fila
                  foreach($fila as $valor){
                    echo "<td>$valor</td>";
                  }

                  //obtenemos el valor de la clave primaria de cada fila
                  $id = $fila[$pk];

                  // Creamos los botones de acción Editar/Eliminar
                  // Escapamos los valores para seguridad
                  $tabla_escaped = htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8');
                  $pk_escaped = htmlspecialchars($pk, ENT_QUOTES, 'UTF-8');
                  $id_escaped = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
                  echo "<td>
                         <button>
                            <a href='editar.php?tabla=" . urlencode($tabla) . "&pk=" . urlencode($pk) . "&id=" . urlencode($id) . "'>Editar</a>
                         </button>
                         <button>
                            <a href='eliminar.php?tabla=" . urlencode($tabla) . "&pk=" . urlencode($pk) . "&id=" . urlencode($id) . "'>Eliminar</a>
                         </button>
                  </td>";

                  echo "</tr>";
               }
            ?>
        </tbody>
    </table>
    
</body>
</html>