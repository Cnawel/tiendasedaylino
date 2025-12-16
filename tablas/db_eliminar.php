<?php 

   include(__DIR__ . '/../config/database.php');

   //obtenemos el nombre de la tabla, la clave primaria y el valor de esa clave
   $tabla = $_GET['tabla'];
   $pk = $_GET['pk'];
   $id = $_GET['id'];

   try {
      //hacemos la consulta para eliminar todos los datos de ese registro
      $eliminar_datos = $mysqli->query("DELETE FROM $tabla WHERE $pk = $id");

      if($eliminar_datos){
         header("Location: db_mostrar_tabla.php?tabla=$tabla");
         exit;
      }else{
         // Si hay error pero no es excepción, redirigir con mensaje de error genérico
         $error_msg = urlencode("Ocurrió un error al tratar de eliminar los datos");
         header("Location: db_mostrar_tabla.php?tabla=$tabla&error=$error_msg");
         exit;
      }
   } catch (mysqli_sql_exception $e) {
      // Capturar excepciones de MySQL, especialmente foreign key constraints
      $error_code = $e->getCode();
      $error_message = $e->getMessage();
      
      // Detectar si es un error de foreign key constraint
      if (strpos($error_message, 'foreign key constraint') !== false || 
          strpos($error_message, 'Cannot delete or update a parent row') !== false) {
         $error_msg = urlencode("No se puede eliminar este registro porque está siendo utilizado en otras tablas (restricción de clave foránea)");
      } else {
         $error_msg = urlencode("Error al eliminar: " . htmlspecialchars($error_message));
      }
      
      // Redirigir de vuelta a la tabla con el mensaje de error
      header("Location: db_mostrar_tabla.php?tabla=$tabla&error=$error_msg");
      exit;
   }

?>