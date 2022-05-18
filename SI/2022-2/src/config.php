<?php
require 'funciones.php';

$dbms = 'mysql';
$host = 'bd.arcelia.net';
$user = 'usarceliaproyect';
$pass = 'Proy3c4r2020%';
$db   = 'bdarceliaproyectos';

$arr_campos_alumno = array('clave_alu', 'clave_admin', 'ap_paterno', 'ap_materno', 
'nombre', 'sexo', 'curp', 'peso', 'estatura', 'direccion', 'colonia', 'cp', 
'ciudad', 'id_estado', 'delegacion', 'telefono', 'celular', 'email', 'status_alu');

$arr_campos_alumno_nn = array('clave_alu','ap_paterno', 'nombre', 'status_alu');

?>