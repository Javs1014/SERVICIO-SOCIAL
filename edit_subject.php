<?php
// Inicia la sesión para acceder a las variables de sesión
session_start();
// Incluye el archivo de conexión a la base de datos
include 'db.php';

// Define los roles autorizados para editar materias
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

// Consulta los datos de la materia con la clave proporcionada
$query = "SELECT clave_materia, nombre FROM materias WHERE clave_materia = ?";
$stmt = $conn->prepare($query); // Prepara la consulta
$stmt->bind_param("s", $clave_materia); // Vincula la clave de la materia como string
$stmt->execute(); // Ejecuta la consulta
$result = $stmt->get_result(); // Obtiene el resultado
$materia = $result->fetch_assoc(); // Almacena los datos de la materia
$stmt->close(); // Cierra el statement

// Verifica si la materia existe
if (!$materia) {
    header("Location: add_user.php"); // Redirige a la página de gestión de materias si no se encuentra
    exit; // Termina la ejecución del script
}

// Procesa el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva_clave = $_POST['clave_materia']; // Obtiene la nueva clave de la materia
    $nombre = $_POST['nombre']; // Obtiene el nombre de la materia

    // Valida que los campos no estén vacíos
    if (!empty($nueva_clave) && !empty($nombre)) {
        // Prepara la consulta para actualizar la materia
        $query = "UPDATE materias SET clave_materia = ?, nombre = ? WHERE clave_materia = ?";
        $stmt = $conn->prepare($query); // Prepara la consulta
        $stmt->bind_param("sss", $nueva_clave, $nombre, $clave_materia); // Vincula los parámetros
        $stmt->execute(); // Ejecuta la consulta
        $stmt->close(); // Cierra el statement
        header("Location: add_user.php"); // Redirige a la página de gestión de materias
        exit; // Termina la ejecución del script
    } else {
        $error = "Todos los campos son obligatorios."; // Mensaje de error si faltan campos
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"> <!-- Define la codificación de caracteres -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Configura el diseño responsivo -->
    <title>Editar Materia</title> <!-- Título de la página -->
    <link rel="stylesheet" href="../styles/styles_addUser.css"> <!-- Enlace al archivo CSS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js" crossorigin="anonymous"></script> <!-- Carga Font Awesome para iconos -->
</head>
<body>
    <!-- Botón de hamburguesa para mostrar/ocultar la barra lateral -->
    <button id="sidebar-toggle" class="sidebar-toggle">
        <i class="fas fa-bars"></i> <!-- Icono de hamburguesa -->
    </button>

    <!-- Barra lateral de navegación -->
    <div class="sidebar" id="sidebar">
        <div>
            <h2>DRWSC-B</h2> <!-- Título del sistema -->
            <p>Desarrollo de repositorio web de <br>sistemas y computación beta</p> <!-- Descripción del sistema -->
        </div>
        <div class="menu">
            <a href="index.php"><i class="fas fa-home"></i> INICIO</a> <!-- Enlace a la página principal -->
            <a href="search_Document.php"><i class="fas fa-search"></i> BUSCAR</a> <!-- Enlace a la búsqueda -->
            <a href="upload_form.php"><i class="fas fa-upload"></i> SUBIR</a> <!-- Enlace para subir archivos -->
            <a href="add_user.php"><i class="fas fa-folder-open"></i> GESTION</a> <!-- Enlace a gestión de usuarios/profesores -->
            <a href="all_Profiles.php"><i class="fas fa-user"></i> PERFILES</a> <!-- Enlace a los perfiles -->

        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a> <!-- Enlace para cerrar sesión -->
    </div>

    <!-- Contenido principal -->
    <div class="content">
        <div class="container">
            <h2>Editar Materia</h2> <!-- Título de la sección -->
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p> <!-- Muestra el mensaje de error, escapado para prevenir XSS -->
            <?php endif; ?>
            <!-- Formulario para editar la materia -->
            <form action="edit_subject.php?clave_materia=<?= urlencode($clave_materia) ?>" method="POST">
                <div class="form-group">
                    <label for="clave_materia">Clave Materia:</label>
                    <input type="text" name="clave_materia" id="clave_materia" value="<?= htmlspecialchars($materia['clave_materia']) ?>" required> <!-- Campo para la clave de la materia -->
                </div>
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" name="nombre" id="nombre" value="<?= htmlspecialchars($materia['nombre']) ?>" required> <!-- Campo para el nombre de la materia -->
                </div>
                <button type="submit" class="btn">Guardar Cambios</button> <!-- Botón para enviar el formulario -->
            </form>
        </div>
    </div>

    <script>
        // Configura el botón de hamburguesa para alternar la barra lateral
        document.getElementById("sidebar-toggle").addEventListener("click", function () {
            document.getElementById("sidebar").classList.toggle("collapsed"); // Alterna la clase 'collapsed'
        });
    </script>
</body>
</html>