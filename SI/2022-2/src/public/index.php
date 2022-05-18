<?php
#Karel Pacheco Ramírez

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpNotFoundException;
use Slim\Slim;

require '../../../vendor/autoload.php';
require '../config.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['dbms'] = $dbms;
$config['db']['host'] = $host;
$config['db']['user'] = $user;
$config['db']['pass'] = $pass;
$config['db']['dbname'] = $db;

#echo ia($config);

$app = new \Slim\App([
  'settings' => $config
]);

$app->options('/{routes:.+}', function ($request, $response, $args) {
  return $response;
});

$container = $app->getContainer();
$container['db'] = function ($c) {
  $db = $c['settings']['db'];
  $pdo = new PDO("{$db['dbms']}:host={$db['host']};dbname={$db['dbname']};charset=utf8", $db['user'], $db['pass']);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
};

$app->get(
  '/hola/{nombre}',
  function (Request $request, Response $response, array $args) {
    $arr = array(
      "success" => true, "method" => "Get",
      "message" => "Hola {$args['nombre']}"
    );

    $response->getBody()->write(json_encode($arr, JSON_UNESCAPED_UNICODE));
    $newResponse = $response->withHeader(
      'Content-Type',
      'application/json; charset=UTF-8'
    );

    return $newResponse;
  }
);

$app->get('/alumnos/{nombre}', function (Request $request, Response $response, array $args) {
  $conn = $this->db;
  $bd = $GLOBALS['bd'];

  $http_status = 200;
  $data = $request->getQueryParams();
  $arrData = str_replace("'", "\"", $data);
  $token = $arrData['token'];

  $arrT = validaToken($token, $bd, $conn);

  if ($arrT['valido']) {
    $sql = "SELECT nombre, ap_paterno, ap_materno 
    FROM alumnos
    WHERE nombre = '{$args['nombre']}'";
    #echo 
    $rs = query($sql, $conn);

    $metadata["items"] = count($rs);
    $arr = array("success" => true, "meta" => $metadata, "data" => $rs);
  } else {
    $arr = array(
      "error" => array(
        "code" => 230,
        "detail" => "No autorizado"
      )
    );

    $http_status = 401;
  }

  $response->getBody()->write(json_encode($arr, JSON_UNESCAPED_UNICODE));

  if ($http_status != 200) {
    $newResponse = $response->withStatus($http_status);
  }

  $newResponse = $response->withHeader(
    'Content-Type',
    'application/json; charset=UTF-8'
  );

  return $newResponse;
});

$app->post('/registro', function (Request $request, Response $response, array $args) {
  $conn = $this->db;
  $bd = $GLOBALS['db'];

  $data = $request->getParsedBody();
  $arrData = str_replace("'", "\"", $data);
  $http_status = 200;

  $arr = array();
  $user = $data['usuario'];
  $email = $data['email'];

  if (!empty($user) && !empty($email)) {
    $sql = "SELECT * 
              FROM {$bd}.usuarios 
              WHERE usuario = '{$user}' or email = '{$email}'";

    $rs = query($sql, $conn);

    if (count($rs) > 0) {
      $arr = array(
        "error" => array(
          "code" => 228,
          "detail" => "usuario o correo ya registrado"
        )
      );

      $http_status = 401;
    } else {
      $today = strtotime(date("Y-m-d H:i:s"));
      $arrUs['token'] = bin2hex(random_bytes(37));
      $arrUs['expira'] = date("Y-m-d H:i:s", strtotime("+1 month", $today));
      $data = [
        'nombres' => $arrData['nombres'],
        'ap_paterno' => $arrData['ap_paterno'],
        'ap_materno' => $arrData['ap_materno'],
        'usuario' => $arrData['usuario'],
        'password' => password_hash($arrData['password'], PASSWORD_DEFAULT),
        'email' => $arrData['email'],
        'tipo_usuario_id' => 3,
        'estado_id' => $arrData['estado'],
        'dependencia_id' => $arrData['dependencia'],
        'codigo_verificacion' => md5("{$today}{$arrData['usuario']}"),
        'token' => $arrUs['token'],
        'token_expira' => $arrUs['expira'],
        'status' => 1,
        'created_at' => date("Y-m-d H:i:s"),
        'updated_at' => date("Y-m-d H:i:s")
      ];
      $sql = "INSERT INTO {$bd}.usuarios 
          SET nombres=:nombres, 
              ap_paterno=:ap_paterno, 
              ap_materno=:ap_materno, 
              usuario=:usuario, 
              password=:password, 
              email=:email, 
              tipo_usuario_id=:tipo_usuario_id, 
              estado_id=:estado_id, 
              dependencia_id=:dependencia_id, 
              codigo_verificacion=:codigo_verificacion, 
              token=:token, 
              token_expira=:token_expira, 
              status=:status, 
              created_at=:created_at, 
              updated_at=:updated_at";
      $stmt = $conn->prepare($sql);
      $stmt->execute($data);
      $error = $conn->errorInfo();
      if (intval($error[0]) != 0) {
        $arr = array(
          "error" => array(
            "code" => 230,
            "detail" => "error al insertar usuario: {$error[1]}"
          )
        );

        $http_status = 401;
      } else {
        $arr = array(
          "success" => true,
          "detail" => "usuario insertado correctamente"
        );
      }
    }
  } else {
    $arr = array(
      "error" => array(
        "code" => 230,
        "detail" => "usuario o email no validos"
      )
    );
    $http_status = 401;
  }

  $response->getBody()->write(json_encode($arr, JSON_UNESCAPED_UNICODE));

  if ($http_status != 200) {
    $newResponse = $response->withStatus($http_status);
  }

  $newResponse = $response->withHeader(
    'Content-Type',
    'application/json; charset=UTF-8'
  );


  return $newResponse;
});

$app->post('/login', function (Request $request, Response $response, array $args) {
  $conn = $this->db;
  $bd = $GLOBALS['db'];

  $data = $request->getParsedBody();
  $arrData = str_replace("'", "\"", $data);
  $http_status = 200;

  $arr = array();
  $user = $data['usuario'];
  $pwd = $data['password'];

  if (!empty($user) && !empty($pwd)) {
    $sql = "SELECT * FROM (SELECT id, usuario, password, email, tipo_usuario_id, status
              FROM {$bd}.usuarios 
              WHERE usuario = '{$user}' or email = '{$user}' and status = 1) u
      LEFT JOIN (SELECT * FROM {$bd}.tokens WHERE active = 1) t 
      ON (u.id = t.usuario_id)";

    $rs = query($sql, $conn);

    if (count($rs) == 0) {
      $arr = array(
        "error" => array(
          "code" => 228,
          "detail" => "usuario no registrado"
        )
      );

      $http_status = 401;
    } else {
      $arrUs = $rs[0];
      if (password_verify($data['password'], $arrUs['password'])) {
        if (empty($arrUs['token'])) {
          $hoy = date("Y-m-d H:i:s");
          $actual = strtotime($hoy);
          $arrUs['token'] = bin2hex(random_bytes(32));
          $arrUs['expires'] = date("Y-m-d H:i:s", strtotime("+1 month", $actual));
          $data = [
            'token' => $arrUs['token'],
            'usuario_id' => $arrUs['id'],
            'expires'  => $arrUs['expires'],
            'active' => 1,
            'created_at' => $hoy,
            'updated_at' => $hoy
          ];
          $sql = "INSERT INTO {$bd}.tokens SET token=:token, usuario_id=:usuario_id, expires=:expires, active=:active, created_at=:created_at, updated_at=:updated_at";
          $stmt = $conn->prepare($sql);
          $stmt->execute($data);
          $error = $conn->errorInfo();
          if (intval($error[0] != 0)) {
            $arr = array("error" => array(
              "code" => '230',
              'detail' => 'Error al insertar token {$error[1]}'
            ));
            $status_http = 401;
          }
        }
        $arr = array(array(
          "token" => $arrUs["token"],
          "expire_time" => $arrUs["expires"]
        ));
      } else {
        $arr = array(
          "error" => array(
            "code" => 229,
            "detail" => "password no valido"
          )
        );
        $http_status = 401;
      }
    }
  } else {
    $arr = array(
      "error" => array(
        "code" => 230,
        "detail" => "usuario no valido"
      )
    );
    $http_status = 401;
  }

  $response->getBody()->write(json_encode($arr, JSON_UNESCAPED_UNICODE));

  if ($http_status != 200) {
    $newResponse = $response->withStatus($http_status);
  }

  $newResponse = $response->withHeader(
    'Content-Type',
    'application/json; charset=UTF-8'
  );


  return $newResponse;
});

$app->get('/alumnos', function (Request $request, Response $response, array $args) {
  $conn = $this->db;
  $bd = $GLOBALS['bd'];

  $http_status = 200;
  $data = $request->getQueryParams();
  $arrData = str_replace("'", "\"", $data);
  $token = $arrData['token'];

  $arrT = validaToken($token, $bd, $conn);

  if(!empty($arrData['nombre'])){
    $whereNombre = " AND(
        CONCAT_WS(' ', ap_paterno, ap_materno, nombre) LIKE '%{$arrData['nombre']}%' 
        OR CONCAT_WS(' ', nombre, ap_paterno, ap_materno) LIKE '%{$arrData['nombre']}%'
    )";
  }

  if(!empty($arrData['estado'])){
    $whereEstado = " AND estado LIKE '%{$arrData['estado']}%'";
  }

  if(!empty($arrData['sexo'])){
    $whereSexo = " AND sexo = '{$arrData['sexo']}'";
  }

  if ($arrT['valido']) {
    $sql = "SELECT clave_alu, clave_admin, ap_paterno, ap_materno, nombre, sexo, curp, peso, estatura, direccion, colonia, cp, ciudad, id_estado, delegacion, telefono, celular, email, status_alu, fedita 
    FROM alumnos d LEFT JOIN estados e ON (d.id_estado = e.id)
    WHERE 1=1 " . $whereNombre . $whereEstado . $whereSexo;
    #echo 
    $rs = query($sql, $conn);

    $metadata["items"] = count($rs);
    $arr = array("success" => true, "meta" => $metadata, "data" => $rs);
  } else {
    $arr = array(
      "error" => array(
        "code" => 230,
        "detail" => "No autorizado"
      )
    );

    $http_status = 401;
  }

  $response->getBody()->write(json_encode($arr, JSON_UNESCAPED_UNICODE));

  if ($http_status != 200) {
    $newResponse = $response->withStatus($http_status);
  }

  $newResponse = $response->withHeader(
    'Content-Type',
    'application/json; charset=UTF-8'
  );

  return $newResponse;
});

$app->post('/alumnos', function (Request $request, Response $response, array $args) {
  $conn = $this->db;
  $bd = $GLOBALS['bd'];

  $http_status = 200;
  $data = $request->getParsedBody();
  $arrData = str_replace("'", "\"", $data);
  $token = $arrData['token'];

  $arrT = validaToken($token, $bd, $conn);

  if ($arrT['valido']) {
    $sql = "SELECT * FROM alumnos WHERE clave_alu = {$arrData['clave_alu']} ";
    $rs = query($sql, $conn);

    if (count($rs) > 0) {
      $arr = array(
        "error" => array(
          "code" => 228,
          "detail" => "Matricula ya registrada"
        )
      );
    } else {
      $valido = true;
      $arr_empty = array();

      foreach ($GLOBALS['arr_campos_alumno_nn'] as $k => $v) {
        if (empty($arrData[$v])) {
          $arr_empty[$v] = "no puede ser nulo";
          $valido = false;
        }
      }

      if ($valido) {
        $data = array();
        $sql = "INSERT INTO {$bd}.alumnos SET\n";
        foreach ($GLOBALS['arr_campos_alumno'] as $k => $v) {
          if (isset($arrData[$v])) {
            $data[$v] = $arrData[$v];
            $sql .= "{$v}=:{$v},\n";
          }
        }
        $sql .= "\nfedita=:fedita";
        $data['fedita'] = strtotime(date('Y-m-d H:i:s'), time());
        $arr = array(
          "success" => true,
          "data" => $rs
        );
        $stmt = $conn->prepare($sql);
        $stmt->execute($data);
        $error = $conn->errorInfo();
        if (intval($error[0] != 0)) {
          $arr = array("error" => array(
            "code" => '230',
            'detail' => 'Error al insertar token {$error[1]}'
          ));
          $status_http = 401;
        }
      } else {
        $arr = array(
          "error" => array(
            "code" => 230,
            "detail" => $arr_empty
          )
        );
      }
    }
  } else {
    $arr = array(
      "error" => array(
        "code" => 230,
        "detail" => "No autorizado"
      )
    );

    $http_status = 401;
  }

  $response->getBody()->write(json_encode($arr, JSON_UNESCAPED_UNICODE));

  if ($http_status != 200) {
    $newResponse = $response->withStatus($http_status);
  }

  $newResponse = $response->withHeader(
    'Content-Type',
    'application/json; charset=UTF-8'
  );

  return $newResponse;
});

$app->run();
