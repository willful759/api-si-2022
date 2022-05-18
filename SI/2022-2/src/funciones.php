<?php
function ia($arr)
{
  $r = "";
  $r = "<pre>" . print_r($arr, true) . "</pre>";
  return $r;
}
function query($sql, $conn)
{
  $q = $conn->query($sql);
  $arr = $q->fetchAll();
  return $arr;
}
function validaToken($token, $bd, $conn)
{
  $arrT = array('valido' => false);
  $sql = "SELECT * FROM (SELECT id, usuario, password, email, tipo_usuario_id, status
              FROM {$bd}.usuarios 
              WHERE status = 1) u
          JOIN (SELECT * FROM {$bd}.tokens WHERE active = 1 and token = '{$token}') t 
      ON (u.id = t.usuario_id)";
  $rs = query($sql, $conn);
  if (count($rs) == 1) {
    $arru = $rs[0];
    if (!empty($arru['id'])) {
      $factual = strtotime(date("Y-m-d H:i:s"), time());
      $fexpira = strtotime($arru['expires']);
      if ($fexpira >= $factual) {
        $arrT['valido'] = true;
        $arrT['usuario_id'] = $arru['id'];
        $arrT['usuario'] = $arru['usuario'];
      }
    }
  }
  return $arrT;
}
?>
