<?php
// Inicia la sesión para acceder a las variables de sesión
session_start();
// Incluye el archivo de conexión a la base de datos
require_once 'db.php';

// Define los roles autorizados para editar profesores
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
// Obtiene el rol del usuario actual en minúsculas, o cadena vacía si no está definido
$current_role = strtolower($_SESSION['rol'] ?? '');

// Verifica si el usuario está autenticado y tiene un rol permitido
if (empty($_SESSION['usuario_id']) || !in_array($current_role, $allowed_roles)) {
    $_SESSION['mensaje'] = "Acceso denegado: No tienes permisos para editar profesores."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script
}

// Obtiene el ID del profesor desde la URL, convirtiéndolo a entero, o null si no está presente
$id_profesor = isset($_GET['id']) ? (int)$_GET['id'] : null;
// Verifica si se proporcionó un ID de profesor
if (!$id_profesor) {
    $_SESSION['mensaje'] = "ID de profesor no proporcionado."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script
}

// Consulta los datos del profesor
$stmt = $conn->prepare("SELECT nombre, id_departamento, anio_ingreso FROM profesores WHERE id_profesor = ?");
$stmt->bind_param("i", $id_profesor); // Vincula el ID del profesor como entero
$stmt->execute(); // Ejecuta la consulta
$result = $stmt->get_result(); // Obtiene el resultado
// Verifica si se encontró el profesor
if ($result->num_rows === 0) {
    $_SESSION['mensaje'] = "Profesor no encontrado."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script
}
$profesor = $result->fetch_assoc(); // Almacena los datos del profesor
$stmt->close(); // Cierra el statement

// Obtiene la lista de departamentos
$departamentos = [];
$query_dept = "SELECT id_departamento, nombre FROM departamento";
$result_dept = $conn->query($query_dept); // Ejecuta la consulta
if ($result_dept) {
    while ($row = $result_dept->fetch_assoc()) {
        $departamentos[] = $row; // Almacena cada departamento en el arreglo
    }
}

// Procesa el formulario de edición si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? ''); // Obtiene y limpia el nombre
    $id_departamento = !empty($_POST['id_departamento']) ? (int)$_POST['id_departamento'] : null; // Obtiene el departamento o null
    $anio_ingreso = !empty($_POST['anio_ingreso']) ? (int)$_POST['anio_ingreso'] : null; // Obtiene el año de ingreso o null

    // Valida los datos
    if (empty($nombre)) {
        $_SESSION['mensaje'] = "El nombre del profesor es obligatorio."; // Mensaje de error
        $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    } elseif ($anio_ingreso !== null && ($anio_ingreso < 1900 || $anio_ingreso > date('Y'))) {
        $_SESSION['mensaje'] = "El año de ingreso debe estar entre 1900 y " . date('Y') . "."; // Mensaje de error
        $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    } else {
        try {
            // Prepara la consulta para actualizar los datos del profesor
            $stmt = $conn->prepare("UPDATE profesores SET nombre = ?, id_departamento = ?, anio_ingreso = ? WHERE id_profesor = ?");
            $stmt->bind_param("siii", $nombre, $id_departamento, $anio_ingreso, $id_profesor); // Vincula los parámetros
            if ($stmt->execute()) { // Ejecuta la consulta
                $_SESSION['mensaje'] = "Profesor actualizado correctamente."; // Mensaje de éxito
                $_SESSION['mensaje_tipo'] = "success"; // Tipo de mensaje
            } else {
                throw new Exception("Error al actualizar el profesor."); // Lanza una excepción si falla
            }
            $stmt->close(); // Cierra el statement
        } catch (Exception $e) {
            $_SESSION['mensaje'] = $e->getMessage(); // Mensaje de error
            $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
        }
        header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
        exit; // Termina la ejecución del script
    }
}

// Cierra la conexión a la base de datos
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"> <!-- Define la codificación de caracteres -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Configura el diseño responsivo -->
    <title>Editar Profesor</title> <!-- Título de la página -->
    <link rel="stylesheet" href="../styles/styles_addUser.css"> <!-- Enlace al archivo CSS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script> <!-- Carga Font Awesome para iconos -->
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
            <a href="all_Profiles.php"><i class="fas fa-user"></i> PERFILES</a> <!-- Enlace a los perfiles -->
            <a href="add_user.php"><i class="fas fa-users-cog"></i> GESTION</a> <!-- Enlace a la gestión de usuarios -->
        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a> <!-- Enlace para cerrar sesión -->
    </div>

    <main class="content">
        <h1>Editar Profesor</h1> <!-- Título de la sección -->

        <!-- Muestra mensajes de éxito o error si existen -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="mensaje <?= $_SESSION['mensaje_tipo'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($_SESSION['mensaje']) ?> <!-- Muestra el mensaje, escapado para prevenir XSS -->
            </div>
            <?php unset($_SESSION['mensaje'], $_SESSION['mensaje_tipo']); ?> <!-- Limpia los mensajes después de mostrarlos -->
        <?php endif; ?>

        <!-- Formulario para editar los datos del profesor -->
        <form method="POST" action="">
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($profesor['nombre']) ?>" required> <!-- Campo para el nombre -->
            </div>
            <div class="form-group">
                <label for="id_departamento">Departamento (Opcional):</label>
                <select id="id_departamento" name="id_departamento"> <!-- Selector para el departamento -->
                    <option value="">Ninguno</option>
                    <?php foreach ($departamentos as $dept): ?>
                        <option value="<?= $dept['id_departamento'] ?>" <?= $profesor['id_departamento'] == $dept['id_departamento'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['nombre']) ?> <!-- Opciones de departamentos -->
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="anio_ingreso">Año de Ingreso (Opcional):</label>
                <input type="number" id="anio_ingreso" name="anio_ingreso" value="<?= htmlspecialchars($profesor['anio_ingreso'] ?? '') ?>" min="1900" max="<?= date('Y') ?>"> <!-- Campo para el año de ingreso -->
            </div>
            <button type="submit" class="btn">Actualizar Profesor</button> <!-- Botón para enviar el formulario -->
            <a href="add_user.php" class="btn cancel-btn">Cancelar</a> <!-- Enlace para cancelar y regresar -->
        </form>
    </main>

    <script>
        // Configura el comportamiento de la barra lateral
        document.addEventListener("DOMContentLoaded", function () {
            let sidebar = document.getElementById("sidebar");
            let content = document.querySelector("main.content");
            let toggleButton = document.getElementById("sidebar-toggle");

            toggleButton.addEventListener("click", function () {
                sidebar.classList.toggle("collapsed"); // Alterna la clase 'collapsed'

                if (sidebar.classList.contains("collapsed")) {
                    content.style.marginLeft = "auto"; // Centra el contenido
                    content.style.marginRight = "auto";
                    content.style.width = "80%";
                } else {
                    content.style.marginLeft = "270px"; // Ajusta el margen para la barra lateral
                    content.style.marginRight = "0";
                    content.style.width = "calc(100% - 270px)"; // Ajusta el ancho del contenido
                }
            });
        });
    </script>
</body>
</html>