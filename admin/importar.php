<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/conexion.php';
redirigirNoAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv'])) {
    $archivo = $_FILES['csv'];
    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $tmpName = $archivo['tmp_name'];
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $error = 'Solo se permiten archivos .csv';
        } else {
            // Eliminar BOM si existe
            $content = file_get_contents($tmpName);
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            file_put_contents($tmpName, $content);

            $handle = fopen($tmpName, 'r');
            $conexion->beginTransaction();

            function formatoIso($f) {
                $d = DateTime::createFromFormat('d/m/Y', trim($f));
                if (!$d) throw new Exception("Fecha inválida: {$f}");
                return $d->format('Y-m-d');
            }

            try {
                $linea = 0;
                $colsEsperadas = 15; // ahora 15 columnas

                // Cargar diccionarios de sistema
                $cats = $conexion->query(
                    "SELECT id_categoria, descripcion FROM categoriasprofesor"
                )->fetchAll(PDO::FETCH_KEY_PAIR);
                $grads = $conexion->query(
                    "SELECT id_grado, nombre_grado FROM gradosacademicos"
                )->fetchAll(PDO::FETCH_KEY_PAIR);

                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $linea++;
                    if ($linea === 1) continue; // saltar encabezado
                    if (count($data) !== $colsEsperadas) {
                        throw new Exception("Error línea {$linea}: Formato incorrecto ({$colsEsperadas} columnas requeridas)");
                    }

                    // Desempaquetar
                    list(
                        $nombre, $rfc, $curp, $gen,
                        $antUnam, $antCarr, $correo,
                        $catDesc, $gradDesc, $telStr,
                        $calle, $numero, $ciudad, $estado, $cp
                    ) = array_map('trim', $data);

                    // Sanitizar y validar
                    $rfc   = strtoupper($rfc);
                    $curp  = strtoupper($curp);
                    $gen   = ucfirst(strtolower($gen));
                    $fUnam = formatoIso($antUnam);
                    $fCarr = formatoIso($antCarr);
                    $mail  = strtolower($correo);
                    $catsInv  = array_flip($cats);
                    $gradsInv = array_flip($grads);

                    if (!isset($catsInv[$catDesc])) {
                        throw new Exception("Línea {$linea}: Categoría no encontrada - {$catDesc}");
                    }
                    if (!isset($gradsInv[$gradDesc])) {
                        throw new Exception("Línea {$linea}: Grado no encontrado - {$gradDesc}");
                    }
                    $idCat  = $catsInv[$catDesc];
                    $idGrad = $gradsInv[$gradDesc];

                    // Insertar trabajador
                    $stmt = $conexion->prepare(
                        "INSERT INTO trabajadores (
                            nombre_completo, rfc, curp, genero,
                            antiguedad_unam, antiguedad_carrera,
                            correo_institucional, id_categoria, id_grado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$nombre,$rfc,$curp,$gen,$fUnam,$fCarr,$mail,$idCat,$idGrad]);
                    $idTrab = $conexion->lastInsertId();

                    // Insertar teléfonos
                    foreach (explode(';', $telStr) as $tel) {
                        $num = preg_replace('/\D/', '', $tel);
                        if (strlen($num) !== 10) {
                            throw new Exception("Línea {$linea}: Teléfono inválido - {$tel}");
                        }
                        $stmtT = $conexion->prepare(
                            "INSERT INTO telefonos (id_trabajador, tipo, numero) VALUES (?, 'Celular', ?)"
                        );
                        $stmtT->execute([$idTrab, $num]);
                    }

                    // Insertar dirección
                    $stmtD = $conexion->prepare(
                        "INSERT INTO direcciones (
                            id_trabajador, calle, numero, ciudad, estado, codigo_postal
                        ) VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $stmtD->execute([$idTrab, $calle, $numero, $ciudad, $estado, $cp]);
                }

                $conexion->commit();
                $success = "¡Importación exitosa! Procesados: " . ($linea-1) . " registros.";

            } catch (Exception $e) {
                $conexion->rollBack();
                $error = $e->getMessage();
            }
            fclose($handle);
        }
    } else {
        $error = "Error al subir archivo: código {$archivo['error']}";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Importar Profesores - FES Aragón</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    :root { --azul-unam: #003366; --oro-unam: #D4AF37; }
    body { background: #f5f5f5; display: flex; min-height: 100vh; flex-direction: column; }
    .nav-wrapper { background-color: var(--azul-unam); padding: 0 20px; }
    .brand-logo img { height: 65px; margin-left: 20px; }
    .upload-card { margin: 2rem auto; max-width: 800px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .btn-unam { background-color: var(--oro-unam)!important; color: var(--azul-unam)!important; font-weight: bold; }
    .alert { padding: 15px; border-radius: 4px; margin: 20px 0; display: flex; align-items: center; }
    .alert.success { background: #dff0d8; color: #3c763d; }
    .alert.error { background: #f2dede; color: #a94442; }
  </style>
</head>
<body>
  <header>
    <nav class="nav-wrapper">
      <div class="container">
        <a href="crud.php" class="brand-logo"><img src="../img/logo-fes.png" alt="FES Aragón"></a>
        <ul class="right hide-on-med-and-down">
          <li><a href="crud.php"><i class="material-icons left">list_alt</i>Regresar</a></li>
          <li><a href="../auth/logout.php"><i class="material-icons left">logout</i>Salir</a></li>
        </ul>
      </div>
    </nav>
  </header>
  <main class="container">
    <div class="upload-card card-panel">
      <h4 class="center-align"><i class="material-icons">cloud_upload</i> Importar Profesores</h4>
      <?php if ($error): ?>
        <div class="alert error"><i class="material-icons">error_outline</i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert success"><i class="material-icons">check_circle</i><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <div class="file-field input-field">
          <div class="btn btn-unam">
            <span>Seleccionar archivo</span>
            <input type="file" name="csv" accept=".csv" required>
          </div>
          <div class="file-path-wrapper">
            <input class="file-path" type="text" placeholder="Haz clic para seleccionar CSV">
          </div>
        </div>
        <div class="center-align">
          <button type="submit" class="btn btn-unam waves-effect">
            <i class="material-icons left">publish</i>Importar
          </button>
        </div>
      </form>
      <div class="card-panel blue lighten-5">
        <h6><i class="material-icons">info</i> Requisitos del CSV:</h6>
        <ul class="browser-default">
          <li>Codificación UTF-8 sin BOM</li>
          <li>Primera línea encabezado (no se importa)</li>
          <li>15 columnas en este orden:
            <ol>
              <li>Nombre completo</li>
              <li>RFC</li>
              <li>CURP</li>
              <li>Género</li>
              <li>Antigüedad UNAM (d/m/Y)</li>
              <li>Antigüedad Carrera (d/m/Y)</li>
              <li>Correo Institucional</li>
              <li>Categoría</li>
              <li>Grado Académico</li>
              <li>Telefonos (separados por ;)</li>
              <li>Calle</li>
              <li>Número</li>
              <li>Ciudad</li>
              <li>Estado</li>
              <li>Código Postal</li>
            </ol>
          </li>
        </ul>
      </div>
    </div>
  </main>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
  <script>document.addEventListener('DOMContentLoaded', ()=>{ M.AutoInit(); });</script>
</body>
</html>
