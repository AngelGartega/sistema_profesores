<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/conexion.php';
redirigirNoAdmin();

// Obtener datos estadísticos
$stats = [];

// 1. Distribución por categoría
$stmt = $conexion->query("
    SELECT cp.descripcion AS categoria, COUNT(*) as total 
    FROM trabajadores t
    JOIN categoriasprofesor cp ON t.id_categoria = cp.id_categoria
    GROUP BY cp.descripcion
");
$stats['categorias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Distribución por género
$stmt = $conexion->query("
    SELECT genero, COUNT(*) as total 
    FROM trabajadores 
    GROUP BY genero
");
$stats['generos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Grados académicos
$stmt = $conexion->query("
    SELECT ga.nombre_grado AS grado, COUNT(*) as total
    FROM trabajadores t
    JOIN gradosacademicos ga ON t.id_grado = ga.id_grado
    GROUP BY ga.nombre_grado
");
$stats['grados'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Antigüedad promedio
$stmt = $conexion->query("
    SELECT 
        AVG(TIMESTAMPDIFF(YEAR, antiguedad_unam, CURDATE())) AS avg_unam,
        AVG(TIMESTAMPDIFF(YEAR, antiguedad_carrera, CURDATE())) AS avg_carrera
    FROM trabajadores
");
$stats['antiguedad'] = $stmt->fetch(PDO::FETCH_ASSOC);

// 5. Teléfonos por trabajador
$stmt = $conexion->query("
    SELECT 
        COUNT(*) AS total_trabajadores,
        SUM(num_telefonos) AS total_telefonos,
        ROUND(AVG(num_telefonos),1) AS promedio
    FROM (
        SELECT t.id_trabajador, COUNT(tel.id_telefono) AS num_telefonos
        FROM trabajadores t
        LEFT JOIN telefonos tel ON t.id_trabajador = tel.id_trabajador
        GROUP BY t.id_trabajador
    ) AS telefonos
");
$stats['telefonos'] = $stmt->fetch(PDO::FETCH_ASSOC);

// 6. Distribución por estado
$stmt = $conexion->query("
    SELECT d.estado, COUNT(*) as total
    FROM direcciones d
    GROUP BY d.estado
    ORDER BY total DESC
    LIMIT 5
");
$stats['estados'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Estadístico - FES Aragón</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --azul-unam: #003366;
            --oro-unam: #D4AF37;
            --verde: #4CAF50;
            --rojo: #F44336;
            --morado: #9C27B0;
        }

        .dashboard {
            padding: 2rem 0;
        }

        .card-stats {
            margin: 1rem;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            min-height: 300px;
        }

        .card-title {
            color: var(--azul-unam);
            border-bottom: 2px solid var(--oro-unam);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 1.4rem;
        }

        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .kpi-box {
            padding: 1.5rem;
            border-radius: 8px;
            color: white;
            text-align: center;
        }

        .kpi-box h5 {
            margin: 0.5rem 0;
            font-size: 1.8rem;
        }

        .kpi-azul { background: var(--azul-unam); }
        .kpi-oro { background: var(--oro-unam); }
        .kpi-verde { background: var(--verde); }
        .kpi-morado { background: var(--morado); }
    </style>
</head>
<body>
    <header>
        <nav class="nav-wrapper" style="background-color: var(--azul-unam);">
            <div class="container">
                <a href="crud.php" class="brand-logo">
                    <img src="../img/logo-fes.png" alt="Logo FES Aragón" style="height: 65px; margin-left: 20px;">
                </a>
                <ul class="right hide-on-med-and-down">
                    <li><a href="crud.php"><i class="material-icons left">arrow_back</i>Regresar al CRUD</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main class="container dashboard">
        <!-- KPI Superiores -->
        <div class="kpi-container">
            <div class="kpi-box kpi-azul">
                <i class="material-icons">people</i>
                <h5><?= array_sum(array_column($stats['categorias'], 'total')) ?></h5>
                <p>Trabajadores Registrados</p>
            </div>
            
            <div class="kpi-box kpi-oro">
                <i class="material-icons">phone</i>
                <h5><?= $stats['telefonos']['total_telefonos'] ?></h5>
                <p>Teléfonos Registrados</p>
            </div>
            
            <div class="kpi-box kpi-verde">
                <i class="material-icons">school</i>
                <h5><?= count($stats['grados']) ?></h5>
                <p>Grados Académicos Diferentes</p>
            </div>
            
            <div class="kpi-box kpi-morado">
                <i class="material-icons">location_city</i>
                <h5><?= count($stats['estados']) ?></h5>
                <p>Estados Registrados</p>
            </div>
        </div>

        <!-- Gráficas -->
        <div class="row">
            <!-- Columna Izquierda -->
            <div class="col s12 m6">
                <div class="card-stats">
                    <div class="card-title">
                        <i class="material-icons">work</i>
                        Distribución por Categoría
                    </div>
                    <canvas id="chartCategorias"></canvas>
                </div>
                
                <div class="card-stats">
                    <div class="card-title">
                        <i class="material-icons">wc</i>
                        Distribución por Género
                    </div>
                    <canvas id="chartGeneros"></canvas>
                </div>
            </div>

            <!-- Columna Derecha -->
            <div class="col s12 m6">
                <div class="card-stats">
                    <div class="card-title">
                        <i class="material-icons">school</i>
                        Grados Académicos
                    </div>
                    <canvas id="chartGrados"></canvas>
                </div>
                
                <div class="card-stats">
                    <div class="card-title">
                        <i class="material-icons">map</i>
                        Top Estados
                    </div>
                    <canvas id="chartEstados"></canvas>
                </div>
            </div>
        </div>

        <!-- Sección Inferior -->
        <div class="row">
            <div class="col s12">
                <div class="card-stats">
                    <div class="card-title">
                        <i class="material-icons">timeline</i>
                        Antigüedad Promedio
                    </div>
                    <div class="row">
                        <div class="col s6 center">
                            <h4><?= round($stats['antiguedad']['avg_unam'], 1) ?> años</h4>
                            <p>En la UNAM</p>
                        </div>
                        <div class="col s6 center">
                            <h4><?= round($stats['antiguedad']['avg_carrera'], 1) ?> años</h4>
                            <p>En la carrera</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Configuración común para gráficas
        Chart.defaults.font.size = 14;
        Chart.defaults.color = '#666';

        // Categorías
        new Chart(document.getElementById('chartCategorias'), {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($stats['categorias'], 'categoria')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($stats['categorias'], 'total')) ?>,
                    backgroundColor: [ '#003366', '#D4AF37', '#4CAF50', '#9C27B0' ]
                }]
            }
        });

        // Géneros
        new Chart(document.getElementById('chartGeneros'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($stats['generos'], 'genero')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($stats['generos'], 'total')) ?>,
                    backgroundColor: [ '#003366', '#D4AF37', '#9C27B0' ]
                }]
            }
        });

        // Grados Académicos
        new Chart(document.getElementById('chartGrados'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($stats['grados'], 'grado')) ?>,
                datasets: [{
                    label: 'Cantidad',
                    data: <?= json_encode(array_column($stats['grados'], 'total')) ?>,
                    backgroundColor: '#003366'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true
            }
        });

        // Estados
        new Chart(document.getElementById('chartEstados'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($stats['estados'], 'estado')) ?>,
                datasets: [{
                    label: 'Trabajadores',
                    data: <?= json_encode(array_column($stats['estados'], 'total')) ?>,
                    borderColor: '#D4AF37',
                    tension: 0.4,
                    fill: false
                }]
            }
        });
    </script>
</body>
</html>