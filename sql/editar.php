<?php  
/**
 * ========================================================================
 * FORMULARIO DE EDICIÓN SQL - Tienda Seda y Lino
 * ========================================================================
 * Permite editar los campos de un registro específico de una tabla
 * ========================================================================
 */
session_start();

// Cargar sistema de autenticación centralizado
require_once __DIR__ . '/../includes/auth_check.php';

// Verificar que el usuario esté logueado y sea admin
requireAdmin();

// Conectar a la base de datos
require_once __DIR__ . '/../config/database.php';

// Obtenemos los parámetros enviados por GET
// Validar y escapar los parámetros para seguridad
$tabla = isset($_GET['tabla']) ? $_GET['tabla'] : '';
$pk = isset($_GET['pk']) ? $_GET['pk'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

// Validar que los parámetros estén presentes
if (empty($tabla) || empty($pk) || empty($id)) {
    header('Location: ../db-tablas.php');
    exit;
}

// Validar que el nombre de la tabla y pk solo contengan caracteres permitidos
if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla) || !preg_match('/^[a-zA-Z0-9_]+$/', $pk)) {
    die('Parámetros inválidos');
}

   // Traemos todos los datos de la tabla para editar que corresponda al registro que se seleccionó, usando la clave primaria y el id
   // Usamos backticks para escapar nombres de tabla y columna (ya validados con regex), y real_escape_string para el ID
   $tabla_escaped = "`" . $tabla . "`";
   $pk_escaped = "`" . $pk . "`";
   $id_escaped = $mysqli->real_escape_string($id);
   $consultar_datos = $mysqli->query("SELECT * FROM $tabla_escaped WHERE $pk_escaped = '$id_escaped'");

   // Obtenemos la metadata de las columnas de la tabla
   $obtenerColumnas = $mysqli->query("SHOW COLUMNS FROM $tabla_escaped");
   //armamos un array asociativo con las columnas para poder acceder al tipo de dato que tiene
   $columnas = [];
   while($col = $obtenerColumnas->fetch_assoc()){
        $columnas[$col['Field']] = $col;
   }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar</title>
</head>
<body>
    
   <h1>Editar <?php echo "Tabla: " . htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8') . " - Registro n°: " . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?></h1>

   <a href="mostrar_tabla.php?tabla=<?php echo urlencode($tabla) ?>">
       <button>← Volver</button>
   </a>
   <br><br>

   <!-- Enviamos el nombre de la tabla, el nombre de la columna que es clave primaria y el valor de la clave por GET -->
   <form action="guardar_editar.php?tabla=<?php echo htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8') ?>&pk=<?php echo htmlspecialchars($pk, ENT_QUOTES, 'UTF-8') ?>&id=<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" method="post">
      <?php 
        //recorremos la fila que se obtuvo en el SELECT de la variable $consultar_datos
         while($fil = $consultar_datos->fetch_assoc()){
            //Recorremos la columna y el valor que tiene ese registro. 
            foreach($fil as $columna => $valor){

                 //detectamos el tipo de dato que tiene esa columna para insertarlo en el input 
                  $type = $columnas[$columna]['Type'];
            
                  if(str_contains($type, "int") || str_contains($type, "decimal") || str_contains($type,"tinyint")){
                     $inputType = "number";
                  }else if(str_contains($type, "datetime")){
                     $inputType = "datetime";
                  }else if(str_contains($type, "date")){
                     $inputType = "date";
                  }else if(str_contains($type, "time")){
                     $inputType = "time";
                  }else{
                     $inputType = "text";
                  }

                 //si la columna es igual a la clave primaria no se edita
                  if($columna == $pk){
                       echo "<label for='$columna'>$columna</label><br>";
                       echo "<input type='number' name='$columna' id='$columna' value='$valor' disabled><br><br>";
                  } else{
                       echo "<label for='$columna'>$columna</label><br>";
                       echo "<input type='$inputType' name='$columna' id='$columna' value='$valor'><br><br>";
                  }
            }
         }
      ?>
      <button>Editar</button>
   </form>
</body>
</html>