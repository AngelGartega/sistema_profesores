<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/conexion.php';
redirigirNoAdmin();

// Manejo de Eliminación
if (isset($_GET['eliminar'])) {
    try {
        $id = limpiarInput($_GET['eliminar']);
        $conexion->beginTransaction();
        
        // Eliminar en cascada
        $conexion->exec("DELETE FROM direcciones WHERE id_trabajador = $id");
        $conexion->exec("DELETE FROM telefonos WHERE id_trabajador = $id");
        $stmt = $conexion->prepare("DELETE FROM trabajadores WHERE id_trabajador = ?");
        $stmt->execute([$id]);
        
        $conexion->commit();
        $_SESSION['mensaje'] = "Registro eliminado correctamente";
    } catch (PDOException $e) {
        $conexion->rollBack();
        $_SESSION['error'] = "Error al eliminar: " . $e->getMessage();
    }
    header("Location: crud.php");
    exit;
}

// Configuración de Columnas y Filtros
$columnas_disponibles = [
    'id_trabajador' => 'ID',
    'nombre_completo' => 'Nombre',
    'rfc' => 'RFC',
    'genero' => 'Género',
    'correo_institucional' => 'Correo Institucional',
    'curp' => 'CURP',
    'antiguedad_unam' => 'Antigüedad UNAM',
    'antiguedad_carrera' => 'Antigüedad Carrera',
    'categoria' => 'Categoría',
    'grado' => 'Grado Académico',
    'telefono' => 'Teléfono',
    'calle' => 'Calle',
    'numero' => 'Número',
    'ciudad' => 'Ciudad',
    'estado' => 'Estado',
    'codigo_postal' => 'Código Postal'
];

$columnas_seleccionadas = $_GET['columnas'] ?? array_keys($columnas_disponibles);
$orden = $_GET['orden'] ?? 'nombre_completo';
$direccion_orden = $_GET['direccion_orden'] ?? 'ASC';
$categoria_filtro = $_GET['categoria'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Validar parámetros
$columnas_validas = array_intersect($columnas_seleccionadas, array_keys($columnas_disponibles));
$orden = in_array($orden, array_keys($columnas_disponibles)) ? $orden : 'nombre_completo';
$direccion_orden = in_array(strtoupper($direccion_orden), ['ASC','DESC']) ? $direccion_orden : 'ASC';

// Consulta principal
$sql = "SELECT 
            t.id_trabajador,
            t.nombre_completo,
            t.rfc,
            t.genero,
            t.curp,
            t.correo_institucional,
            TIMESTAMPDIFF(YEAR, t.antiguedad_unam, CURDATE()) AS antiguedad_unam,
            TIMESTAMPDIFF(YEAR, t.antiguedad_carrera, CURDATE()) AS antiguedad_carrera,
            cp.descripcion AS categoria,
            ga.nombre_grado AS grado,
            GROUP_CONCAT(tel.numero SEPARATOR ', ') AS telefono,
            d.calle,
            d.numero,
            d.ciudad,
            d.estado,
            d.codigo_postal
        FROM trabajadores t
        LEFT JOIN categoriasprofesor cp ON t.id_categoria = cp.id_categoria
        LEFT JOIN gradosacademicos ga ON t.id_grado = ga.id_grado
        LEFT JOIN telefonos tel ON t.id_trabajador = tel.id_trabajador
        LEFT JOIN direcciones d ON t.id_trabajador = d.id_trabajador
        WHERE 1=1";


$params = [];

// Filtros
if (!empty($categoria_filtro)) {
    $sql .= " AND cp.descripcion = ?";
    $params[] = $categoria_filtro;
}

if (!empty($busqueda)) {
    $sql .= " AND (t.nombre_completo LIKE ? OR t.rfc LIKE ?)";
    $term = "%" . trim($busqueda) . "%";
    $params[] = $term;
    $params[] = $term;
}

$sql .= " GROUP BY t.id_trabajador";
$sql .= " ORDER BY $orden $direccion_orden";

try {
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// Obtener categorías para el filtro
$categorias = $conexion->query("SELECT descripcion FROM categoriasprofesor")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gestión de Trabajadores - FES Aragón</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    :root {
      --azul-unam: #003366;
      --oro-unam: #D4AF37;
    }
    body { background: #f9f9f9; }
    .nav-wrapper { background-color: var(--azul-unam); padding: 0 20px; }
    .brand-logo img { height: 65px; margin-left: 20px; }
    .filters-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 1.5rem;
      margin: 2rem 0;
    }
    .filter-group {
      background: #fff;
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .filter-group.columns { min-width: 360px; }
    .filter-title {
      color: var(--azul-unam);
      font-size: 1.2rem;
      border-bottom: 2px solid var(--oro-unam);
      padding-bottom: 0.5rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .table-container {
      overflow-x: auto;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border-radius: 8px;
      margin: 2rem 0;
    }
    table { min-width: 1000px; width: 100%; border-collapse: collapse; }
    th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
    th { background: var(--azul-unam); color: #fff; position: sticky; top: 0; }
    tr:hover { background: #f1f1f1; }
    .btn-unam { background-color: var(--oro-unam)!important; color: var(--azul-unam)!important; }
    .btn-reset { background-color: #bdbdbd!important; color: #fff!important; margin-left: 1rem; }
    .sort-indicator { margin-left: 0.5rem; vertical-align: middle; }
    .numero { font-family: monospace; }
    .antiguedad { text-align: center; }
  </style>
</head>
<body>
  <header>
    <nav class="nav-wrapper">
      <div class="container">
        <a href="#" class="brand-logo">
          <img src="../img/logo-fes.png" alt="Logo FES Aragón">
        </a>
        <ul class="right hide-on-med-and-down">
          <li><a href="graficas.php"><i class="material-icons left">insert_chart</i>Estadísticas</a></li>
          <li><a href="../auth/logout.php"><i class="material-icons left">exit_to_app</i>Salir</a></li>
        </ul>
      </div>
    </nav>
  </header>

  <main class="container">
    <?php if (isset($_SESSION['mensaje'])): ?>
      <div class="card-panel green lighten-4 green-text text-darken-4">
        <i class="material-icons left">check_circle</i>
        <?= $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="card-panel red lighten-4 red-text text-darken-4">
        <i class="material-icons left">error</i>
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <form method="GET">
      <div class="filters-container">
        <!-- Filtros Principales -->
        <div class="filter-group">
          <h5 class="filter-title"><i class="material-icons">tune</i>Filtros Principales</h5>
          <div class="input-field">
            <select name="categoria">
              <option value=""<?= $categoria_filtro==''?' selected':'' ?>>Todas las categorías</option>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['descripcion'] ?>"<?= $categoria_filtro==$cat['descripcion']?' selected':'' ?>>
                  <?= $cat['descripcion'] ?>
                </option>
              <?php endforeach; ?>
            </select>
            <label>Categoría</label>
          </div>
          <div class="input-field">
            <input type="text" name="busqueda" id="busqueda" 
                   value="<?= htmlspecialchars($busqueda) ?>" 
                   placeholder="Buscar por nombre o RFC">
            <label for="busqueda" class="active">Buscar</label>
          </div>
        </div>

        <!-- Ordenamiento -->
        <div class="filter-group">
          <h5 class="filter-title"><i class="material-icons">sort</i>Ordenamiento</h5>
          <div class="input-field">
            <select name="orden">
              <?php foreach ($columnas_disponibles as $key=>$label): ?>
                <option value="<?= $key ?>"<?= $orden==$key?' selected':'' ?>>
                  <?= $label ?>
                </option>
              <?php endforeach; ?>
            </select>
            <label>Ordenar por</label>
          </div>
          <div class="input-field">
            <select name="direccion_orden">
              <option value="ASC"<?= $direccion_orden=='ASC'?' selected':'' ?>>Ascendente</option>
              <option value="DESC"<?= $direccion_orden=='DESC'?' selected':'' ?>>Descendente</option>
            </select>
            <label>Dirección</label>
          </div>
        </div>

        <!-- Columnas -->
        <div class="filter-group columns">
			<h5 class="filter-title"><i class="material-icons">view_column</i>Columnas</h5>
			<div class="row" style="margin:0;">
			<?php foreach ($columnas_disponibles as $key => $label): ?>
				<div class="col s6">
					<label>
					<input type="checkbox" name="columnas[]" value="<?= $key ?>" <?= in_array($key,$columnas_validas)?'checked':'' ?>>
                    <span><?= $label ?></span>
					</label>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<br>
      
	  <div class="right-align" style="margin-bottom:2rem;">
        <button type="submit" class="btn btn-unam waves-effect">
          <i class="material-icons left">filter_alt</i>Aplicar Filtros
        </button>
        <a href="crud.php" class="btn btn-reset waves-effect">
          <i class="material-icons left">refresh</i>Restablecer
        </a>
      </div>
    </form>
	</div>

    <!-- Tabla de Trabajadores -->
    <div class="table-container">
      <table class="highlight">
        <thead>
          <tr>
            <?php foreach ($columnas_validas as $col): ?>
              <th class="<?= strpos($col, 'antiguedad') !== false ? 'antiguedad' : '' ?>">
                <?= $columnas_disponibles[$col] ?>
                <?php if ($orden==$col): ?>
                  <i class="material-icons sort-indicator">
                    <?= $direccion_orden==='ASC'?'arrow_drop_up':'arrow_drop_down' ?>
                  </i>
                <?php endif; ?>
              </th>
            <?php endforeach; ?>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
    <?php foreach ($trabajadores as $t): ?>
        <tr>
            <?php foreach ($columnas_validas as $col): ?>
                <td class="<?= 
                    ($col === 'id_trabajador' ? 'numero' : '') . 
                    (strpos($col, 'antiguedad') !== false ? ' antiguedad' : '')
                ?>">
                    <?= htmlspecialchars($t[$col] ?? 'N/A') ?>
                </td>
            <?php endforeach; ?>
            <td>
                 <a href="actualizar.php?id=<?= $t['id_trabajador'] ?>" class="btn btn-small btn-unam waves-effect">
                  <i class="material-icons">edit</i>
                </a>
                <a href="#" onclick="confirmarEliminacion(<?= $t['id_trabajador'] ?>)" class="btn btn-small red waves-effect">
                  <i class="material-icons">delete</i>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
	</tbody>
      </table>
    </div>
	

    <!-- Botones de Acción -->
    <div class="right-align" style="margin:1rem 0;">
      <a href="importar.php" class="btn btn-unam waves-effect">
        <i class="material-icons left">publish</i>Importar
      </a>
      <a href="descargar.php?<?= http_build_query([
            'columnas'=>$columnas_validas,
            'orden'=>$orden,
            'direccion_orden'=>$direccion_orden,
            'categoria'=>$categoria_filtro,
            'busqueda'=>$busqueda
          ]) ?>" class="btn btn-unam waves-effect" style="margin-left:1rem;">
        <i class="material-icons left">download</i>Exportar
      </a>
    </div>

    <div class="fixed-action-btn">
      <a href="actualizar.php?nuevo=1" class="btn-floating btn-large red pulse">
        <i class="large material-icons">add</i>
      </a>
    </div>
  </main>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      M.AutoInit();
      $('select').formSelect();
    });

    function confirmarEliminacion(id) {
      if (confirm('¿Estás seguro que deseas eliminar este trabajador y todos sus datos relacionados?')) {
        window.location.href = `crud.php?eliminar=${id}`;
      }
    }
  </script>
</body>
</html>