<?php 

   include("config/database.example.php");

   //obtenemos el nombre de la tabla, la clave primaria y el valor de esa clave
   $tabla = $_GET['tabla'];
   $pk = $_GET['pk'];
   $id = $_GET['id'];

   //hacemos la consulta para eliminar todos los datos de ese registro
   $eliminar_datos = $mysqli->query("DELETE FROM $tabla WHERE $pk = $id");

   if($eliminar_datos){
      header("Location: db_mostrar_tabla.php?tabla=$tabla");
   }else{
      echo "Ocurrio un error al tratar de eliminar los datos";
   }

?>