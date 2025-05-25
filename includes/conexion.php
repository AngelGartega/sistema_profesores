<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$servidor = "";
$usuario = "";
$password = "";
$bd = "";

try {
    $conexion = new PDO(
        "mysql:host=$servidor;dbname=$bd", 
        $usuario, 
        $password
    );
    
    // Configurar modo de errores
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Codificación de caracteres
    $conexion->exec("SET NAMES 'utf8'");

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage()); // Mensaje detallado
}
?>