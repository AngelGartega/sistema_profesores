<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

function estaAutenticado() {
    return isset($_SESSION['usuario_id']);
}

function esAdmin() {
    return estaAutenticado() && $_SESSION['rol'] === 'admin';
}

function limpiarInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function redirigirNoAutenticado() {
    if (!estaAutenticado() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header('Location: /sistema_profesores/auth/login.php');
        exit;
    }
}

function redirigirNoAdmin() {
    redirigirNoAutenticado();
    if (!esAdmin()) {
        header('Location: /sistema_profesores/usuario/consulta.php');
        exit;
    }
}

function obtenerDestinoPorRol($rol) {
    if ($rol === 'admin') {
        return '/sistema_profesores/admin/crud.php';
    } else {
        return '/sistema_profesores/usuario/consulta.php';
    }
}
?>