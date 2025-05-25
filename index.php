<?php
session_start();

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/seguridad.php';

if (!estaAutenticado()) {
    // Si no está autenticado, lo mando al login
    header("Location: auth/login.php");
    exit;
} else {
    // Si ya está autenticado, lo mando a la página adecuada según su rol
    $destino = obtenerDestinoPorRol($_SESSION['rol']);
    header("Location: $destino");
    exit;
}
