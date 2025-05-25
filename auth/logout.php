<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
// Iniciar sesión antes de destruirla para manipular variables
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Eliminar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redireccionar con mensaje usando parámetro GET
header('Location: login.php?mensaje=Sesión cerrada correctamente');
exit;
?>