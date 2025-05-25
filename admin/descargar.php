<?php
session_start();
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';
redirigirNoAutenticado();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// OBTENER PARÁMETROS
$columnas = $_GET['columnas'] ?? [];
$orden = $_GET['orden'] ?? 'nombre_completo';
$direccion_orden = $_GET['direccion_orden'] ?? 'ASC';
$categoria_filtro = $_GET['categoria'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// COLUMNAS DISPONIBLES CON DIRECCIÓN
$columnas_disponibles = [
    'id_trabajador' => 'ID',
    'nombre_completo' => 'Nombre',
    'rfc' => 'RFC',
    'curp' => 'CURP',
    'genero' => 'Género',
    'antiguedad_unam' => 'Antigüedad UNAM (años)',
    'antiguedad_carrera' => 'Antigüedad Carrera (años)',
    'correo_institucional' => 'Correo Institucional',
    'categoria' => 'Categoría',
    'grado' => 'Grado Académico',
    'telefonos' => 'Teléfonos',
    'calle' => 'Calle',
    'numero' => 'Número',
    'ciudad' => 'Ciudad',
    'estado' => 'Estado',
    'codigo_postal' => 'Código Postal'
];

// VALIDAR
$columnas_validas = array_intersect($columnas, array_keys($columnas_disponibles));
if (empty($columnas_validas)) $columnas_validas = array_keys($columnas_disponibles);
$orden = in_array($orden, array_keys($columnas_disponibles)) ? $orden : 'nombre_completo';
$direccion_orden = in_array(strtoupper($direccion_orden), ['ASC','DESC']) ? strtoupper($direccion_orden) : 'ASC';

try {
    // RESETEAR AUTO_INCREMENT
    $maxId = (int)$conexion->query("SELECT MAX(id_trabajador) FROM trabajadores")->fetchColumn();
    $conexion->exec("ALTER TABLE trabajadores AUTO_INCREMENT = " . ($maxId + 1));

    // CONSULTA INCLUYENDO DIRECCIÓN
    $sql = "SELECT 
                t.id_trabajador,
                t.nombre_completo,
                t.rfc,
                t.curp,
                t.genero,
                TIMESTAMPDIFF(YEAR, t.antiguedad_unam, CURDATE()) AS antiguedad_unam,
                TIMESTAMPDIFF(YEAR, t.antiguedad_carrera, CURDATE()) AS antiguedad_carrera,
                t.correo_institucional,
                cp.descripcion AS categoria,
                ga.nombre_grado AS grado,
                GROUP_CONCAT(DISTINCT tel.numero SEPARATOR ', ') AS telefonos,
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
    if ($categoria_filtro !== '') {
        $sql .= " AND cp.descripcion = ?";
        $params[] = $categoria_filtro;
    }
    if ($busqueda !== '') {
        $sql .= " AND (t.nombre_completo LIKE ? OR t.rfc LIKE ? OR t.curp LIKE ? OR d.calle LIKE ? OR d.ciudad LIKE ?)";
        $term = "%" . trim($busqueda) . "%";
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
    }
    $sql .= " GROUP BY t.id_trabajador ORDER BY $orden $direccion_orden";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // GENERAR EXCEL
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Encabezados
    $colLetter = 'A';
    foreach ($columnas_validas as $col) {
        $sheet->setCellValue("{$colLetter}1", $columnas_disponibles[$col]);
        $colLetter++;
    }

    // Datos
    $row = 2;
    foreach ($trabajadores as $t) {
        $colLetter = 'A';
        foreach ($columnas_validas as $col) {
            $sheet->setCellValue("{$colLetter}{$row}", $t[$col] ?? 'N/A');
            $colLetter++;
        }
        $row++;
    }

    // Autoajustar
    foreach (range('A', $colLetter) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // DESCARGAR
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="trabajadores.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    die("Error al exportar: " . $e->getMessage());
}
?>
