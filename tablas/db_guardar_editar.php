<?php  

  include(__DIR__ . '/../config/database.php');

  //obtenemos los nombres de la tabla, la clave primaria y el valor de esa clave
  $tabla = $_GET['tabla'];
  $pk = $_GET['pk'];
  $id = $_GET['id'];

  //obtenemos los valores que se enviaron por el formulario
  $valores = $_POST;

  //eliminamos la pk para no recibirla en el array
  unset($valores[$pk]);

  //creamos un array para los valores 
  $set = [];
  //recorremos el array y asociamos el valor con su columna
  foreach($valores as $columna => $valor){
      $valor = mysqli_real_escape_string($mysqli, $valor);
      $set[] = " $columna = '$valor' ";
  }

  //separamos los datos que estan en el array con una coma
  $set_array = implode(",", $set);

  //creamos la sentencia sql para editar los datos 
  $editar_sql = "UPDATE $tabla SET $set_array WHERE $pk = $id";

  if($mysqli->query($editar_sql)){
     header("Location: db_mostrar_tabla.php?tabla=$tabla");
  }else{
     echo "<h3 style='color: red;'>Ocurrio un error al editar el registro</h3>";
  }

?>