<?php
// Inicia la sesión del usuario para poder acceder a sus variables de sesión
session_start();

// Incluye el archivo de conexión a la base de datos
include '../db.php';

// Define los roles autorizados para acceder a esta página
$allowed_roles = ['Administrador', 'Jefe', 'Coordinador','admin'];

// Verifica si el rol de la sesión está definido y si está en la lista de roles permitidos
$is_authorized = isset($_SESSION['rol']) && in_array($_SESSION['rol'], $allowed_roles);

// Si no está autorizado, redirige al usuario a la página de inicio (index.php)
if (!$is_authorized) {
    header("Location: index.php");
    exit;
}

// Procesa el formulario solo si la petición es de tipo POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtiene los valores enviados desde el formulario
    $clave_materia = $_POST['clave_materia'];
    $nombre = $_POST['nombre'];

    // Verifica que los campos no estén vacíos
    if (!empty($clave_materia) && !empty($nombre)) {
        // Prepara la consulta SQL para insertar una nueva materia
        $query = "INSERT INTO materias (clave_materia, nombre) VALUES (?, ?)";
        $stmt = $conn->prepare($query); // Prepara la sentencia para prevenir inyecciones SQL
        $stmt->bind_param("ss", $clave_materia, $nombre); // Vincula los parámetros con el tipo "string"
        $stmt->execute(); // Ejecuta la consulta
        $stmt->close(); // Cierra el statement

        // Redirige a la página de administración de materias
        header("Location: manage_subjects.php");
        exit;
    } else {
        // Si hay campos vacíos, se define el mensaje de error
        $error = "Todos los campos son obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Metadatos del documento -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Materia</title>

    <!-- Hoja de estilos personalizada -->
    <link rel="stylesheet" href="../styles/styles_Allp.css">

    <!-- Script de íconos de FontAwesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js" crossorigin="anonymous"></script>
</head>
<body>

    <!-- Botón tipo hamburguesa para mostrar/ocultar el menú lateral -->
    <button id="sidebar-toggle" class="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Barra lateral de navegación -->
    <div class="sidebar" id="sidebar">
        <div>
            <!-- Título del sistema -->
            <h2>DRWSC-B</h2>
            <p>Desarrollo de repositorio web de <br>sistemas y computación beta</p>
        </div>

        <!-- Menú de navegación -->
        <div class="menu">
            <a href="index.php"><i class="fas fa-home"></i> INICIO</a>
            <a href="search_Document.php"><i class="fas fa-search"></i> BUSCAR</a>
            <a href="upload_form.php"><i class="fas fa-upload"></i> SUBIR</a>
            <a href="add_user.php"><i class="fas fa-folder-open"></i> GESTION</a>
            <a href="all_Profiles.php"><i class="fas fa-user"></i> PERFILES</a>
            <a href="manage_subjects.php"><i class="fas fa-book"></i> MATERIAS</a>
        </div>

        <!-- Botón para cerrar sesión -->
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a>
    </div>

    <!-- Contenedor principal del contenido -->
    <div class="content">
        <div class="container">
            <h2>Agregar Nueva Materia</h2>

            <!-- Mostrar mensaje de error si existe -->
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <!-- Formulario para agregar una nueva materia -->
            <form action="add_subject.php" method="POST">
                <div class="form-group">
                    <label for="clave_materia">Clave Materia:</label>
                    <input type="text" name="clave_materia" id="clave_materia" required>
                </div>
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" name="nombre" id="nombre" required>
                </div>
                <button type="submit" class="add-btn">Agregar Materia</button>
            </form>
        </div>
    </div>

    <!-- Script para mostrar u ocultar la barra lateral al hacer clic en el botón hamburguesa -->
    <script>
        document.getElementById("sidebar-toggle").addEventListener("click", function () {
            document.getElementById("sidebar").classList.toggle("collapsed");
        });
    </script>
</body>
</html>
