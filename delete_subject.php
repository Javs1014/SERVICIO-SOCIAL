<?php
// Inicia la sesión para acceder a las variables de sesión, como el rol del usuario
session_start();
// Incluye el archivo de conexión a la base de datos
include 'db.php';

// Define los roles autorizados para realizar la acción de eliminar materias
$allowed_roles = ['Administrador', 'Jefe', 'Coordinador'];
// Verifica si el usuario tiene un rol definido y está autorizado
$is_authorized = isset($_SESSION['rol']) && in_array($_SESSION['rol'], $allowed_roles);

// Si el usuario no está autorizado, redirige a la página principal
if (!$is_authorized) {
    header("Location: index.php"); // Redirige a index.php
    exit; // Termina la ejecución del script
}

// Obtiene la clave de la materia desde la URL, o null si no está presente
$clave_materia = $_GET['clave_materia'] ?? null;
// Verifica si se proporcionó una clave de materia
if (!$clave_materia) {
    header("Location: add_user.php"); // Redirige a la página de gestión de materias
    exit; // Termina la ejecución del script
}

// Prepara una consulta para obtener el nombre de la materia con la clave proporcionada
$query = "SELECT nombre FROM materias WHERE clave_materia = ?";
$stmt = $conn->prepare($query); // Prepara la consulta
$stmt->bind_param("s", $clave_materia); // Vincula la clave de la materia como string
$stmt->execute(); // Ejecuta la consulta
$result = $stmt->get_result(); // Obtiene el resultado
$materia = $result->fetch_assoc(); // Obtiene los datos de la materia
$stmt->close(); // Cierra el statement

// Verifica si la materia existe y si su nombre no contiene "servicio" (case-insensitive)
if ($materia && stripos($materia['nombre'], 'servicio') === false) {
    // Prepara una consulta para eliminar la materia de la base de datos
    $query = "DELETE FROM materias WHERE clave_materia = ?";
    $stmt = $conn->prepare($query); // Prepara la consulta
    $stmt->bind_param("s", $clave_materia); // Vincula la clave de la materia
    $stmt->execute(); // Ejecuta la consulta para eliminar
    $stmt->close(); // Cierra el statement
}

// Redirige a la página de gestión de materias
header("Location: add_user.php");
exit; // Termina la ejecución del script
?>