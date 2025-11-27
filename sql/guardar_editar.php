<?php  
/**
 * ========================================================================
 * GUARDAR EDICIÓN SQL - Tienda Seda y Lino
 * ========================================================================
 * Procesa y guarda los cambios realizados en un registro de una tabla
 * ========================================================================
 */
session_start();

// Cargar sistema de autenticación centralizado
require_once __DIR__ . '/../includes/auth_check.php';

// Verificar que el usuario esté logueado y sea admin
requireAdmin();

// Conectar a la base de datos
require_once __DIR__ . '/../config/database.php';

// Obtenemos los nombres de la tabla, la clave primaria y el valor de esa clave
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

  // Separamos los datos que están en el array con una coma
  $set_array = implode(",", $set);

  // Creamos la sentencia SQL para editar los datos
  // Usamos backticks para escapar nombres de tabla y columna (ya validados con regex), y real_escape_string para el ID
  $tabla_escaped = "`" . $tabla . "`";
  $pk_escaped = "`" . $pk . "`";
  $id_escaped = $mysqli->real_escape_string($id);
  $editar_sql = "UPDATE $tabla_escaped SET $set_array WHERE $pk_escaped = '$id_escaped'";

  if($mysqli->query($editar_sql)){
     header("Location: mostrar_tabla.php?tabla=" . urlencode($tabla));
     exit;
  }else{
     echo "<h3 style='color: red;'>Ocurrió un error al editar el registro: " . htmlspecialchars($mysqli->error, ENT_QUOTES, 'UTF-8') . "</h3>";
     echo "<a href='../db-tablas.php'><button>Volver</button></a>";
  }

?>