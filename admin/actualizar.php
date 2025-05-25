<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/conexion.php';
redirigirNoAdmin();

// Obtener datos del trabajador si es edición
$trabajador = ['telefonos' => []];
$id_trabajador = $_GET['id'] ?? null;

if ($id_trabajador) {
    try {
        $sql = "SELECT 
            t.*, 
            GROUP_CONCAT(tel.numero) AS telefonos,
            cp.id_categoria,
            ga.id_grado,
            d.calle,
            d.numero AS num_direccion,
            d.ciudad,
            d.estado,
            d.codigo_postal
        FROM trabajadores t
        LEFT JOIN telefonos tel ON t.id_trabajador = tel.id_trabajador
        LEFT JOIN categoriasprofesor cp ON t.id_categoria = cp.id_categoria
        LEFT JOIN gradosacademicos ga ON t.id_grado = ga.id_grado
        LEFT JOIN direcciones d ON t.id_trabajador = d.id_trabajador
        WHERE t.id_trabajador = ?
        GROUP BY t.id_trabajador";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$id_trabajador]);
        $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);
        $trabajador['telefonos'] = $trabajador['telefonos'] ? explode(',', $trabajador['telefonos']) : [];
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al cargar datos: " . $e->getMessage();
        header('Location: crud.php');
        exit;
    }
}

// Obtener opciones para selects
$categorias = $conexion->query("SELECT * FROM categoriasprofesor")->fetchAll();
$grados = $conexion->query("SELECT * FROM gradosacademicos")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datos = [
        'nombre_completo' => limpiarInput($_POST['nombre_completo']),
        'genero' => limpiarInput($_POST['genero']),
        'rfc' => limpiarInput($_POST['rfc']),
        'curp' => limpiarInput($_POST['curp']),
        'antiguedad_unam' => limpiarInput($_POST['antiguedad_unam']),
        'antiguedad_carrera' => limpiarInput($_POST['antiguedad_carrera']),
        'correo_institucional' => limpiarInput($_POST['correo_institucional']),
        'id_categoria' => limpiarInput($_POST['id_categoria']),
        'id_grado' => limpiarInput($_POST['id_grado']),
			'calle' => limpiarInput($_POST['calle']),
		'numero_direccion' => limpiarInput($_POST['numero_direccion']),
		'ciudad' => limpiarInput($_POST['ciudad']),
		'estado' => limpiarInput($_POST['estado']),
		'codigo_postal' => limpiarInput($_POST['codigo_postal']),
        'telefonos' => array_filter($_POST['telefonos'] ?? [])

    ];

    try {
        $conexion->beginTransaction();

        if (isset($_POST['id_trabajador'])) { // Modo edición
            $id_trabajador = limpiarInput($_POST['id_trabajador']);
            
            // Actualizar trabajador
            $sql = "UPDATE trabajadores SET
                        nombre_completo = ?,
                        genero = ?,
                        rfc = ?,
                        curp = ?,
                        antiguedad_unam = ?,
                        antiguedad_carrera = ?,
                        correo_institucional = ?,
                        id_categoria = ?,
                        id_grado = ?
                    WHERE id_trabajador = ?";
            
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                $datos['nombre_completo'],
                $datos['genero'],
                $datos['rfc'],
                $datos['curp'],
                $datos['antiguedad_unam'],
                $datos['antiguedad_carrera'],
                $datos['correo_institucional'],
                $datos['id_categoria'],
                $datos['id_grado'],
                $id_trabajador
            ]);
            
            // Eliminar teléfonos antiguos
            $conexion->exec("DELETE FROM telefonos WHERE id_trabajador = $id_trabajador");
            
        } else { // Modo creación
            $sql = "INSERT INTO trabajadores (
                        nombre_completo,
                        genero,
                        rfc,
                        curp,
                        antiguedad_unam,
                        antiguedad_carrera,
                        correo_institucional,
                        id_categoria,
                        id_grado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                $datos['nombre_completo'],
                $datos['genero'],
                $datos['rfc'],
                $datos['curp'],
                $datos['antiguedad_unam'],
                $datos['antiguedad_carrera'],
                $datos['correo_institucional'],
                $datos['id_categoria'],
                $datos['id_grado']
            ]);
            $id_trabajador = $conexion->lastInsertId();
        }

        // Insertar nuevos teléfonos
        if (!empty($datos['telefonos'])) {
            $sql = "INSERT INTO telefonos (id_trabajador, tipo, numero) VALUES ";
            $values = [];
            $params = [];
            
            foreach ($datos['telefonos'] as $tel) {
                $values[] = "(?, 'Celular', ?)";
                $params[] = $id_trabajador;
                $params[] = limpiarInput($tel);
            }
            
            $stmt = $conexion->prepare($sql . implode(',', $values));
            $stmt->execute($params);
        }
		
// Manejar dirección
if ($id_trabajador) {
    // Verificar si existe dirección
    $existeDireccion = $conexion->query("SELECT 1 FROM direcciones WHERE id_trabajador = $id_trabajador")->fetchColumn();
    
    if ($existeDireccion) {
        $sqlDireccion = "UPDATE direcciones SET
            calle = ?,
            numero = ?,
            ciudad = ?,
            estado = ?,
            codigo_postal = ?
            WHERE id_trabajador = ?";
        $paramsDireccion = [
            $datos['calle'],
            $datos['numero_direccion'],
            $datos['ciudad'],
            $datos['estado'],
            $datos['codigo_postal'],
            $id_trabajador
        ];
    } else {
        $sqlDireccion = "INSERT INTO direcciones (
            id_trabajador,
            calle,
            numero,
            ciudad,
            estado,
            codigo_postal
        ) VALUES (?, ?, ?, ?, ?, ?)";
        $paramsDireccion = [
            $id_trabajador,
            $datos['calle'],
            $datos['numero_direccion'],
            $datos['ciudad'],
            $datos['estado'],
            $datos['codigo_postal']
        ];
    }
} else {
    $sqlDireccion = "INSERT INTO direcciones (
        id_trabajador,
        calle,
        numero,
        ciudad,
        estado,
        codigo_postal
    ) VALUES (?, ?, ?, ?, ?, ?)";
    $paramsDireccion = [
        $id_trabajador,
        $datos['calle'],
        $datos['numero_direccion'],
        $datos['ciudad'],
        $datos['estado'],
        $datos['codigo_postal']
    ];
}

$stmtDireccion = $conexion->prepare($sqlDireccion);
$stmtDireccion->execute($paramsDireccion);


        $conexion->commit();
        $_SESSION['mensaje'] = "Registro " . (isset($_POST['id_trabajador']) ? 'actualizado' : 'creado') . " correctamente";
        header('Location: crud.php');
        exit;

    } catch (PDOException $e) {
        $conexion->rollBack();
        $_SESSION['error'] = "Error en la operación: " . $e->getMessage();
        header('Location: crud.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id_trabajador ? 'Editar' : 'Nuevo' ?> Trabajador - FES Aragón</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --azul-unam: #003366;
            --oro-unam: #D4AF37;
        }

        .nav-wrapper {
            background-color: var(--azul-unam) !important;
            padding: 0 20px;
        }

        .brand-logo img {
            height: 65px;
            margin-left: 20px;
        }

        .form-container {
            margin-top: 2rem;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .input-field label.active {
            color: var(--azul-unam) !important;
        }

        .telefonos-container .row {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <header>
        <nav class="nav-wrapper">
            <div class="container">
                <a href="crud.php" class="brand-logo">
                    <img src="../img/logo-fes.png" alt="Logo FES Aragón">
                </a>
                <ul class="right hide-on-med-and-down">
                    <li><a href="crud.php"><i class="material-icons left">arrow_back</i>Regresar</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="form-container card-panel">
            <h4 class="center-align blue-text text-darken-4">
                <i class="material-icons">edit</i>
                <?= $id_trabajador ? 'Editar Trabajador' : 'Nuevo Trabajador' ?>
            </h4>

            <form method="POST">
                <?php if ($id_trabajador): ?>
                    <input type="hidden" name="id_trabajador" value="<?= $id_trabajador ?>">
                <?php endif; ?>

                <div class="row">
                    <!-- Datos Básicos -->
                    <div class="col s12 m6">
                        <div class="input-field">
                            <i class="material-icons prefix">person</i>
                            <input id="nombre_completo" type="text" name="nombre_completo" required
                                   value="<?= htmlspecialchars($trabajador['nombre_completo'] ?? '') ?>">
                            <label for="nombre_completo">Nombre completo</label>
                        </div>

                        <div class="input-field">
                            <i class="material-icons prefix">wc</i>
                            <select name="genero" required>
                                <option value="" disabled>Seleccione género</option>
                                <option value="Masculino" <?= ($trabajador['genero'] ?? '') == 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                                <option value="Femenino" <?= ($trabajador['genero'] ?? '') == 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                                <option value="Otro" <?= ($trabajador['genero'] ?? '') == 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                            <label>Género</label>
                        </div>

                        <div class="input-field">
                            <i class="material-icons prefix">assignment</i>
                            <input id="rfc" type="text" name="rfc" required
                                   value="<?= htmlspecialchars($trabajador['rfc'] ?? '') ?>">
                            <label for="rfc">RFC</label>
                        </div>

                        <div class="input-field">
                            <i class="material-icons prefix">fingerprint</i>
                            <input id="curp" type="text" name="curp" required
                                   value="<?= htmlspecialchars($trabajador['curp'] ?? '') ?>">
                            <label for="curp">CURP</label>
                        </div>
                    </div>

                    <!-- Datos Institucionales -->
                    <div class="col s12 m6">
                        <div class="input-field">
                            <i class="material-icons prefix">date_range</i>
                            <input id="antiguedad_unam" type="date" name="antiguedad_unam" required
                                   value="<?= htmlspecialchars($trabajador['antiguedad_unam'] ?? '') ?>">
                            <label for="antiguedad_unam">Fecha de ingreso UNAM</label>
                        </div>

                        <div class="input-field">
                            <i class="material-icons prefix">date_range</i>
                            <input id="antiguedad_carrera" type="date" name="antiguedad_carrera" required
                                   value="<?= htmlspecialchars($trabajador['antiguedad_carrera'] ?? '') ?>">
                            <label for="antiguedad_carrera">Fecha de ingreso a carrera</label>
                        </div>

                        <div class="input-field">
                            <i class="material-icons prefix">work</i>
                            <select name="id_categoria" required>
                                <option value="" disabled>Seleccione categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id_categoria'] ?>" 
                                        <?= ($cat['id_categoria'] == ($trabajador['id_categoria'] ?? '')) ? 'selected' : '' ?>>
                                        <?= $cat['descripcion'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label>Categoría</label>
                        </div>

                        <div class="input-field">
                            <i class="material-icons prefix">school</i>
                            <select name="id_grado" required>
                                <option value="" disabled>Seleccione grado</option>
                                <?php foreach ($grados as $grado): ?>
                                    <option value="<?= $grado['id_grado'] ?>" 
                                        <?= ($grado['id_grado'] == ($trabajador['id_grado'] ?? '')) ? 'selected' : '' ?>>
                                        <?= $grado['nombre_grado'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label>Grado académico</label>
                        </div>
						
						<div class="input-field">
							<i class="material-icons prefix">email</i>
							<input id="correo_institucional" type="email" name="correo_institucional" required
							value="<?= htmlspecialchars($trabajador['correo_institucional'] ?? '') ?>"
							pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
							<label for="correo_institucional">Correo Institucional</label>
						</div>

                    </div>
                
				<div class="col s12">
    <div class="card-panel blue lighten-5">
        <h5 class="blue-text text-darken-4">
            <i class="material-icons">location_on</i>
            Dirección
        </h5>
        
        <div class="row">
            <div class="input-field col s12 m6">
                <i class="material-icons prefix">place</i>
                <input id="calle" type="text" name="calle" required
                       value="<?= htmlspecialchars($trabajador['calle'] ?? '') ?>">
                <label for="calle">Calle</label>
            </div>
            
            <div class="input-field col s12 m3">
                <i class="material-icons prefix">looks_one</i>
                <input id="numero_direccion" type="text" name="numero_direccion"
                       value="<?= htmlspecialchars($trabajador['num_direccion'] ?? '') ?>">
                <label for="numero_direccion">Número</label>
            </div>
            
            <div class="input-field col s12 m3">
                <i class="material-icons prefix">location_city</i>
                <input id="ciudad" type="text" name="ciudad" required
                       value="<?= htmlspecialchars($trabajador['ciudad'] ?? '') ?>">
                <label for="ciudad">Ciudad</label>
            </div>
            
            <div class="input-field col s12 m4">
                <i class="material-icons prefix">map</i>
                <select name="estado" required>
                    <option value="">Seleccione estado</option>
                    <?php 
                    $estados = ['CDMX', 'Estado de México', 'Morelos', 'Puebla', 'Hidalgo'];
                    foreach ($estados as $est): ?>
                        <option value="<?= $est ?>" <?= ($trabajador['estado'] ?? '') == $est ? 'selected' : '' ?>>
                            <?= $est ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Estado</label>
            </div>
            
            <div class="input-field col s12 m4">
                <i class="material-icons prefix">markunread_mailbox</i>
                <input id="codigo_postal" type="text" name="codigo_postal" required
                       pattern="\d{5}"
                       value="<?= htmlspecialchars($trabajador['codigo_postal'] ?? '') ?>">
                <label for="codigo_postal">Código Postal</label>
            </div>
        </div>
    </div>
</div>
				
				</div>
				

                <!-- Teléfonos -->
                <div class="row telefonos-container">
                    <div class="col s12">
                        <h5 class="blue-text text-darken-4">
                            <i class="material-icons">phone</i>
                            Números de teléfono
                        </h5>
                        
                        <div id="telefonos-wrapper">
                            <?php foreach ($trabajador['telefonos'] as $index => $tel): ?>
                            <div class="row phone-row">
                                <div class="input-field col s11">
                                    <i class="material-icons prefix">dialpad</i>
                                    <input type="tel" 
                                        name="telefonos[]" 
                                        pattern="[0-9]{10}"
                                        id="phone_<?= $index ?>"
                                        value="<?= htmlspecialchars($tel) ?>">
                                    <label for="phone_<?= $index ?>">Teléfono <?= $index + 1 ?></label> <!-- Clase active agregada -->
                                </div>
                                <div class="col s1 valign-wrapper">
                                    <a class="btn-floating red waves-effect waves-light remove-phone">
                                        <i class="material-icons">remove</i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        
                        <div class="right-align">
                            <a class="btn-floating green" id="add-phone">
                                <i class="material-icons">add</i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="center-align">
                    <button type="submit" class="btn waves-effect blue darken-4">
                        <i class="material-icons left">save</i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </main>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar todos los componentes de Materialize
            M.AutoInit();
            $('select').formSelect();

            // Validación automática de RFC y CURP
            document.getElementById('codigo_postal').addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '').substring(0,5);
            });
            
            document.getElementById('correo_institucional').addEventListener('input', function(e) {
                this.value = this.value.toLowerCase();
            });

            // Plantilla para nuevos teléfonos
            const phoneTemplate = `
                <div class="row phone-row">
                    <div class="input-field col s11">
                        <i class="material-icons prefix">dialpad</i>
                        <input type="tel" name="telefonos[]" pattern="[0-9]{10}" class="validate" required>
                        <label>Nuevo teléfono</label>
                    </div>
                    <div class="col s1">
                        <a class="btn-floating red remove-phone waves-effect">
                            <i class="material-icons">remove</i>
                        </a>
                    </div>
                </div>`;

            // Añadir nuevo teléfono
            $('#add-phone').on('click', function(e) {
                e.preventDefault();
                $('#telefonos-wrapper').append(phoneTemplate);
                phoneCounter++;
                M.updateTextFields(); // Actualiza los labels
                $(`#phone-nuevo-${phoneCounter - 1}`).focus(); // Foco automático
                
                // Reinicializar componentes
                M.updateTextFields();
                $('input[name="telefonos[]"]').last().focus();
            });

            // Eliminar teléfono
            $(document).on('click', '.remove-phone', function(e) {
            e.preventDefault();
            $(this).closest('.phone-row').remove(); // Buscar por la clase correcta
            });

            // Validación numérica para teléfonos
            $(document).on('input', 'input[name="telefonos[]"]', function() {
                this.value = this.value.replace(/\D/g, '').substring(0,10);
            });
        });
    </script>
</body>
</html>
