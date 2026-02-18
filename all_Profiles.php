<?php
// Inicia la sesión para acceder a las variables de sesión, como el rol del usuario
session_start(); 
// Incluye el archivo de conexión a la base de datos
include './db.php';

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
// Maneja la subida de imágenes de perfil si el usuario está autorizado y la solicitud es válida
if ($is_authorized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']) && isset($_POST['id_profesor'])) {
    $id_profesor = (int)$_POST['id_profesor']; // Obtiene y convierte el ID del profesor a entero
    $target_dir = "./img/profesores/"; // Directorio donde se almacenarán las imágenes
    $file_name = basename($_FILES['profile_image']['name']); // Obtiene el nombre del archivo subido
    $target_file = $target_dir . $file_name; // Ruta completa del archivo en el servidor
    $relative_path = "../img/profesores/" . $file_name; // Ruta relativa para almacenar en la base de datos
    $upload_ok = true; // Bandera para verificar si la subida es válida
    $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION)); // Obtiene la extensión del archivo en minúsculas

    // Valida si el archivo es una imagen
    $check = getimagesize($_FILES['profile_image']['tmp_name']);
    if ($check === false) {
        $upload_ok = false;
        $error = "El archivo no es una imagen."; // Mensaje de error si no es una imagen
    }

    // Verifica el tamaño del archivo (máximo 5MB)
    if ($_FILES['profile_image']['size'] > 5000000) {
        $upload_ok = false;
        $error = "El archivo es demasiado grande."; // Mensaje de error si excede el tamaño
    }

    // Restringe los formatos de archivo permitidos
    if (!in_array($image_file_type, ['jpg', 'jpeg', 'png', 'gif'])) {
        $upload_ok = false;
        $error = "Solo se permiten archivos JPG, JPEG, PNG y GIF."; // Mensaje de error si el formato no es válido
    }

    // Verifica si el archivo ya existe en el servidor
    if (file_exists($target_file)) {
        $upload_ok = false;
        $error = "El archivo ya existe."; // Mensaje de error si el archivo ya está en el servidor
    }

    // Si todas las validaciones son correctas, intenta subir el archivo
    if ($upload_ok) {
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            // Actualiza la ruta de la imagen en la base de datos
            $query = "UPDATE profesores SET imagen = ? WHERE id_profesor = ?";
            $stmt = $conn->prepare($query); // Prepara la consulta
            $stmt->bind_param("si", $relative_path, $id_profesor); // Vincula los parámetros
            $stmt->execute(); // Ejecuta la consulta
            $stmt->close(); // Cierra el statement
        } else {
            $error = "Error al subir el archivo."; // Mensaje de error si falla la subida
        }
    }
}

// Obtiene el filtro de profesor si está presente en la URL
$filtro_profesor = isset($_GET['id_profesor']) ? (int)$_GET['id_profesor'] : null;

// Prepara la consulta para obtener los datos de los profesores
$query = "SELECT id_profesor, nombre, imagen FROM profesores"; // Consulta base para obtener todos los profesores
if ($filtro_profesor) {
    $query .= " WHERE id_profesor = ?"; // Añade condición si hay un filtro por ID de profesor
}

$stmt = $conn->prepare($query); // Prepara la consulta
if ($filtro_profesor) {
    $stmt->bind_param("i", $filtro_profesor); // Vincula el ID del profesor al parámetro
}
$stmt->execute(); // Ejecuta la consulta
$result = $stmt->get_result(); // Obtiene el resultado

// Verifica si la consulta fue exitosa
if (!$result) {
    die("Error en la consulta: " . $conn->error); // Termina la ejecución si hay un error
}

// Almacena los datos de los profesores en un arreglo
$profesores = [];
while ($row = $result->fetch_assoc()) {
    $profesores[] = [
        "id_profesor" => $row['id_profesor'],
        "nombre" => $row['nombre'],
        "imagen" => $row['imagen'] ?: "../img/profesores/default.jpg" // Usa una imagen predeterminada si no hay imagen
    ];
}

$stmt->close(); // Cierra el statement
$conn->close(); // Cierra la conexión a la base de datos
?>

<!--CODIGO DE LA PAGINA WEB ------->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"> <!-- Define la codificación de caracteres -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Configura el diseño responsivo -->
    <title>Perfiles de Profesores</title> <!-- Título de la página -->
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
            <a href="add_user.php"><i class="fas fa-folder-open"></i>GESTION</a> <!-- Enlace a la gestión de usuarios/profesores -->
            <a href="all_Profiles.php"><i class="fas fa-user"></i> PERFILES</a> <!-- Enlace a los perfiles -->
        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a> <!-- Enlace para cerrar sesión -->
    </div>

    <!-- Contenido principal -->
    <div class="content">
        <div class="container">
            <div class="profiles-title-container">
                <h2 class="profiles-title">Perfiles de Profesores</h2> <!-- Título de la sección -->
            </div>

                    <div class="filters-upload-wrapper">
                        <!-- Contenedor para los filtros -->
                        <div class="filters-container"> <!-- Filtro para seleccionar un profesor -->
                            <h3 class="form-title">Buscar Profesor</h3>
                            <div class="filter-group">
                                <select id="id_profesor" class="filter-select">
                                    <option value="">Todos los profesores</option> <!-- Opción para mostrar todos -->
                                    <?php foreach ($profesores as $prof): ?> <!-- Itera sobre los profesores -->
                                        <option value="<?= $prof['id_profesor'] ?>" 
                                                <?= ($filtro_profesor == $prof['id_profesor']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prof['nombre']) ?> <!-- Muestra el nombre del profesor -->
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Botón para realizar la búsqueda -->
                            <div class="filter-group">
                                <button class="search-btn" onclick="buscarDocumentos()">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>

                            <!-- Botón para limpiar el filtro -->
                            <?php if ($filtro_profesor): ?>
                            <div class="filter-group">
                                <a href="all_Profiles.php" class="clear-filter">
                                    <i class="fas fa-times"></i> Limpiar filtro
                                </a> <!-- Enlace para eliminar el filtro -->
                            </div>
                            <?php endif; ?>
                        </div>  
                            <!-- Formulario para subir imágenes de perfil (visible solo para usuarios autorizados) -->
                         <?php if ($is_authorized): ?>
                        <div class="upload-form-container">
                                <!-- Título del formulario -->
                                <?php if (isset($error)): ?>
                                    <p class="error"><?php echo htmlspecialchars($error); ?></p> <!-- Muestra mensaje de error si existe -->
                                <?php endif; ?>
                                <form action="all_Profiles.php" method="POST" enctype="multipart/form-data"> <!-- Formulario para subir imagen -->
                                    
                                    <div class="form-group">
                                        <h3 class="upload-title">Subir Imagen de Perfil</h3>
                                        <label for="id_profesor_upload">Seleccionar Profesor:</label>
                                        <select name="id_profesor" id="id_profesor_upload" required> <!-- Selector para elegir profesor -->
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($profesores as $prof): ?> <!-- Itera sobre los profesores -->
                                                <option value="<?= $prof['id_profesor'] ?>">
                                                    <?= htmlspecialchars($prof['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="profile_image">Seleccionar Imagen:</label>
                                        <input type="file" name="profile_image" id="profile_image" accept="image/*" required> <!-- Campo para seleccionar la imagen -->
                                    </div>
                                    <button type="submit" class="upload-btn">Subir Imagen</button> <!-- Botón para enviar el formulario -->
                                </form>
                                </div>

                                <?php endif; ?>
                        </div>
                    </div>                            
                <!-- Listado de perfiles de profesores -->
                <div class="profiles-container">
                    <?php 
                    $grupos = array_chunk($profesores, 3); // Divide los profesores en grupos de 3 para mostrar en filas
                    foreach ($grupos as $grupo): ?>
                        <div class="profile-row">
                            <?php foreach ($grupo as $profesor): ?> <!-- Itera sobre cada grupo de profesores -->
                                <a href="detail_Profiles.php?id=<?= $profesor['id_profesor'] ?>" class="profile-card-link">
                                    <div class="profile-card">
                                        <img src="<?= htmlspecialchars($profesor['imagen']) ?>" 
                                            alt="<?= htmlspecialchars($profesor['nombre']) ?>" 
                                            class="profile-image"> <!-- Imagen del profesor -->
                                        <h3><?= htmlspecialchars($profesor['nombre']) ?></h3> <!-- Nombre del profesor -->
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
    </div>

    <script>
        // Agrega un evento al botón de hamburguesa para alternar la barra lateral
        document.getElementById("sidebar-toggle").addEventListener("click", function () {
            document.getElementById("sidebar").classList.toggle("collapsed"); // Alterna la clase 'collapsed'
        });

        // Función para realizar la búsqueda según el filtro seleccionado
        function buscarDocumentos() {
            const profesorId = document.getElementById('id_profesor').value; // Obtiene el ID del profesor seleccionado
            if (profesorId) {
                window.location.href = 'all_Profiles.php?id_profesor=' + profesorId; // Redirige con el filtro
            } else {
                window.location.href = 'all_Profiles.php'; // Redirige sin filtro
            }
        }
    </script>
</body>
</html>