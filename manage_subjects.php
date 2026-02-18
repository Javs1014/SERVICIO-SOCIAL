<?php
// Inicia la sesión para acceder a las variables de sesión
session_start();
// Incluye el archivo de conexión a la base de datos
include 'db.php';

// Define roles permitidos para acceder a la gestión de usuarios y verifica el rol del usuario actual
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
$current_role = strtolower($_SESSION['rol'] ?? '');
$is_logged_in = isset($_SESSION['usuario_id']);
$is_authorized = $is_logged_in && in_array($current_role, $allowed_roles);

// Verifica si el usuario está autenticado y tiene un rol permitido
if (empty($_SESSION['usuario_id']) || !in_array($current_role, $allowed_roles)) {
    $_SESSION['mensaje'] = "Acceso denegado: No tienes permisos para estar aquí.";
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: index.php"); // Redirige al inicio si no tiene permisos
    exit; // Termina la ejecución del script
}
// Consulta todas las materias ordenadas por clave_materia
$query = "SELECT clave_materia, nombre FROM materias ORDER BY clave_materia";
$stmt = $conn->prepare($query); // Prepara la consulta
$stmt->execute(); // Ejecuta la consulta
$result = $stmt->get_result(); // Obtiene el resultado

// Verifica si la consulta falló
if (!$result) {
    die("Error en la consulta: " . $conn->error); // Termina con un mensaje de error
}

// Almacena las materias en un arreglo
$materias = [];
while ($row = $result->fetch_assoc()) {
    $materias[] = [
        "clave_materia" => $row['clave_materia'], // Clave de la materia
        "nombre" => $row['nombre'] // Nombre de la materia
    ];
}

$stmt->close(); // Cierra el statement
$conn->close(); // Cierra la conexión a la base de datos
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"> <!-- Define la codificación de caracteres -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Configura el diseño responsivo -->
    <title>Gestionar Materias</title> <!-- Título de la página -->
    <link rel="stylesheet" href="../styles/styles_Allp.css"> <!-- Enlace al archivo CSS -->
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
            <a href="manage_subjects.php"><i class="fas fa-book"></i> MATERIAS</a> <!-- Enlace a gestión de materias -->
        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a> <!-- Enlace para cerrar sesión -->
    </div>

    <!-- Contenido principal -->
    <div class="content">
        <div class="container">
            <h2>Lista de Materias</h2> <!-- Título de la sección -->
            <table>
                <thead>
                    <tr>
                        <th>Clave Materia</th> <!-- Encabezado para la clave de la materia -->
                        <th>Nombre</th> <!-- Encabezado para el nombre de la materia -->
                        <?php if ($is_authorized): ?>
                            <th>Acciones</th> <!-- Encabezado para las acciones, solo visible para usuarios autorizados -->
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materias as $materia): ?> <!-- Itera sobre las materias -->
                        <tr>
                            <td><?= htmlspecialchars($materia['clave_materia']) ?></td> <!-- Muestra la clave de la materia, escapada para prevenir XSS -->
                            <td><?= htmlspecialchars($materia['nombre']) ?></td> <!-- Muestra el nombre de la materia, escapado -->
                            <?php if ($is_authorized): ?> <!-- Muestra acciones si el usuario está autorizado -->
                                <td>
                                    <a href="edit_subject.php?clave_materia=<?= urlencode($materia['clave_materia']) ?>">Editar</a> <!-- Enlace para editar la materia -->
                                    <?php if (stripos($materia['nombre'], 'servicio') === false): ?> <!-- Oculta la opción de eliminar si el nombre contiene "servicio" -->
                                        <a href="delete_subject.php?clave_materia=<?= urlencode($materia['clave_materia']) ?>" 
                                           onclick="return confirm('¿Estás seguro de eliminar esta materia?')">Eliminar</a> <!-- Enlace para eliminar con confirmación -->
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($is_authorized): ?>
                <a href="add_subject.php" class="add-btn">Agregar Materia</a> <!-- Enlace para agregar una nueva materia, solo para usuarios autorizados -->
            <?php endif; ?>
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