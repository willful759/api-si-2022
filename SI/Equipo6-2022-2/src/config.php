<?php
require 'funciones.php';

$dbms = 'mysql';
$host = 'bd.arcelia.net';
$user = 'usarceliaproyect';
$pass = 'Proy3c4r2020%';
$db   = 'bdarceliaproyectos';

$arr_campos_alumno = array('nombre', 'apellido_paterno', 'apellido_materno',
  'matricula', 'grado', 'grupo');

$arr_campos_alumno_nn = $arr_campos_alumno; //array('clave_alu','ap_paterno', 'nombre', 'status_alu');

?>
