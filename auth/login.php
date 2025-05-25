<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/seguridad.php';

// Redirigir usuarios autenticados
if (estaAutenticado()) {
    $destino = obtenerDestinoPorRol($_SESSION['rol']);
    header("Location: $destino");
    exit;
}

// Manejar mensajes temporales
if (isset($_GET['mensaje'])) {
    $_SESSION['mensaje_temporal'] = $_GET['mensaje'];
    header("Location: login.php"); // Limpiar parámetro de URL
    exit;
}

// Procesar formulario
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = limpiarInput($_POST['username']);
    $password = limpiarInput($_POST['password']);

    try {
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() == 1) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $usuario['password'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['username'] = $usuario['username'];
                $_SESSION['rol'] = $usuario['rol'];
                header('Location: ' . obtenerDestinoPorRol($usuario['rol']));
                exit;
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario no encontrado";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - FES Aragón</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --azul-unam: #003366;
            --oro-unam: #D4AF37;
            --rojo-fes: #8B0000;
        }

        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            background-color: #f5f5f5;
        }

        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .brand-logo {
            text-align: center;
            margin: 50;
        }

        .brand-logo img {
            height: 65px;
            margin-left: 20px;
        }

        .alert-auto-close {
            animation: fadeOut 1s ease-in 5s forwards;
            opacity: 1;
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; display: none; }
        }
    </style>
</head>
<body>
    <header>
        <nav class="nav-wrapper" style="background-color: var(--azul-unam);">
            <div class="container">
                <div class="brand-logo">
                    <img src="../img/logo-fes.png" alt="Logo FES Aragón">
                </div>
            </div>
        </nav>
    </header>

    <main class="login-container">
        <div class="login-card card-panel">
            <?php if(isset($_SESSION['mensaje_temporal'])): ?>
                <div class="alert success alert-auto-close">
                    <i class="material-icons left">check_circle</i>
                    <?= htmlspecialchars($_SESSION['mensaje_temporal']) ?>
                </div>
                <?php unset($_SESSION['mensaje_temporal']); ?>
            <?php endif; ?>

            <?php if(!empty($error)): ?>
                <div class="alert error">
                    <i class="material-icons left">error</i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <h4 class="center-align" style="color: var(--azul-unam);">
                <i class="material-icons">lock</i> Acceso al Sistema
            </h4>

            <form method="POST">
                <div class="row">
                    <div class="input-field col s12">
                        <i class="material-icons prefix">person</i>
                        <input id="username" type="text" name="username" class="validate" required>
                        <label for="username">Nombre de usuario</label>
                    </div>

                    <div class="input-field col s12">
                        <i class="material-icons prefix">vpn_key</i>
                        <input id="password" type="password" name="password" class="validate" required>
                        <label for="password">Contraseña</label>
                    </div>

                    <div class="center-align" style="margin-top: 2rem;">
                        <button type="submit" class="btn waves-effect blue darken-4">
                            <i class="material-icons left">login</i>Ingresar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        // Limpiar parámetros de la URL
        if (window.history.replaceState) {
            const cleanURL = window.location.pathname;
            window.history.replaceState({}, document.title, cleanURL);
        }

        // Auto-eliminar mensajes después de 5 segundos
        setTimeout(() => {
            document.querySelectorAll('.alert-auto-close').forEach(alert => {
                alert.remove();
            });
        }, 5000);

        // Inicializar componentes Materialize
        document.addEventListener('DOMContentLoaded', function() {
            M.AutoInit();
        });
    </script>
</body>
</html>