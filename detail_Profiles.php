<?php
// Inicia una sesión segura si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // Establece la duración de la cookie de sesión a 24 horas
        'cookie_secure' => true, // Cambiar a true en producción para HTTPS, asegura que la cookie solo se envíe por conexiones seguras
        'cookie_httponly' => true, // Previene acceso a la cookie desde JavaScript, aumentando la seguridad
        'use_strict_mode' => true // Activa el modo estricto para prevenir ataques de fijación de sesión
    ]);

    // Verifica si la sesión es nueva o ha expirado (1800 segundos = 30 minutos)
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time(); // Registra el momento de creación de la sesión
    } elseif (time() - $_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true); // Regenera el ID de la sesión para mitigar ataques de fijación
        $_SESSION['CREATED'] = time(); // Actualiza el tiempo de creación
    }
}

// Incluye el archivo de conexión a la base de datos
include './db.php';

// Inicia el almacenamiento en búfer de salida para evitar problemas con cabeceras
ob_start();

// Obtiene el ID del profesor desde la URL, convirtiéndolo a entero, o null si no está presente
$id_profesor = isset($_GET['id']) ? (int)$_GET['id'] : null;
// Verifica si se proporcionó un ID de profesor
if (!$id_profesor) {
    header("Location: all_Profiles.php"); // Redirige a la página de perfiles si no hay ID
    exit; // Termina la ejecución del script
}

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

// Consulta la información principal del profesor, incluyendo el nombre del departamento
$query_profesor = "SELECT p.*, d.nombre AS nombre_departamento 
                  FROM profesores p
                  LEFT JOIN departamento d ON p.id_departamento = d.id_departamento
                  WHERE p.id_profesor = ?";
$stmt_profesor = $conn->prepare($query_profesor); // Prepara la consulta
if (!$stmt_profesor) {
    error_log("Error al preparar la consulta de profesores: " . $conn->error); // Registra el error
    die("Error en la base de datos. Por favor, intenta de nuevo más tarde."); // Termina con mensaje de error
}
$stmt_profesor->bind_param("i", $id_profesor); // Vincula el ID del profesor como entero
if (!$stmt_profesor->execute()) { // Ejecuta la consulta
    error_log("Error al ejecutar la consulta de profesores: " . $stmt_profesor->error); // Registra el error
    die("Error en la base de datos. Por favor, intenta de nuevo más tarde."); // Termina con mensaje de error
}
$result_profesor = $stmt_profesor->get_result(); // Obtiene el resultado

// Verifica si se encontró un profesor
if ($result_profesor->num_rows === 0) {
    error_log("No se encontró profesor con id_profesor: $id_profesor"); // Registra el error
    header("Location: all_Profiles.php"); // Redirige a la página de perfiles
    exit; // Termina la ejecución del script
}

$profesor = $result_profesor->fetch_assoc(); // Obtiene los datos del profesor
if (!$profesor) {
    error_log("No se pudo obtener datos del profesor para id_profesor: $id_profesor"); // Registra el error
    header("Location: all_Profiles.php"); // Redirige a la página de perfiles
    exit; // Termina la ejecución del script
}
$stmt_profesor->close(); // Cierra el statement

// Consulta los documentos asociados al profesor
$query_documentos = "SELECT * FROM documentos WHERE id_profesor = ?";
$stmt_documentos = $conn->prepare($query_documentos); // Prepara la consulta
if (!$stmt_documentos) {
    error_log("Error al preparar la consulta de documentos: " . $conn->error); // Registra el error
    die("Error en la base de datos. Por favor, intenta de nuevo más tarde."); // Termina con mensaje de error
}
$stmt_documentos->bind_param("i", $id_profesor); // Vincula el ID del profesor
$stmt_documentos->execute(); // Ejecuta la consulta
$result_documentos = $stmt_documentos->get_result(); // Obtiene el resultado

// Inicializa un arreglo para organizar documentos por categorías
$documentos = [
    "Doctorado" => [],
    "Maestría" => [],
    "Licenciatura" => [],
    "Cursos" => []
];

// Procesa cada documento y lo asigna a la categoría correspondiente
while ($row = $result_documentos->fetch_assoc()) {
    $tipo_documento = $row['tipo_documento']; // Obtiene el tipo de documento
    $categoria = null;

    // Verifica si el tipo_documento comienza con "otros_"
    if (stripos($tipo_documento, 'otros_') === 0) {
        $subtipo = substr($tipo_documento, strlen('otros_')); // Extrae el subtipo
        switch (strtolower($subtipo)) {
            case 'doctorado':
                $categoria = 'Doctorado'; // Asigna a la categoría Doctorado
                break;
            case 'maestria':
                $categoria = 'Maestría'; // Asigna a la categoría Maestría
                break;
            case 'licenciatura':
                $categoria = 'Licenciatura'; // Asigna a la categoría Licenciatura
                break;
            case 'cursos':
            case 'otros':
            default:
                $categoria = 'Cursos'; // Asigna a la categoría Cursos por defecto
                break;
        }
    } else {
        // Asigna la categoría según palabras clave en el tipo_documento
        if (stripos($tipo_documento, 'Doctorado') !== false) {
            $categoria = 'Doctorado';
        } elseif (stripos($tipo_documento, 'Maestría') !== false) {
            $categoria = 'Maestría';
        } elseif (stripos($tipo_documento, 'Licenciatura') !== false) {
            $categoria = 'Licenciatura';
        } else {
            $categoria = 'Cursos';
        }
    }

    // Si se determinó una categoría, agrega el documento al arreglo
    if ($categoria) {
        $documentos[$categoria][] = [
            "id_documento" => $row['id_documento'], // ID del documento
            "titulo" => $row['tipo_documento'] ?? "Sin título", // Título del documento
            "universidad" => "No especificada", // Universidad (valor fijo por ahora)
            "anio" => $row['anio'] ?? "Sin especificar", // Año del documento
            "imagen" => "./uploads/" . $row['imagen'], // Ruta de la imagen
            "archivo" => "./uploads/" . $row['imagen'] // Ruta del archivo (mismo que imagen)
        ];
    }
}
$stmt_documentos->close(); // Cierra el statement

// Obtiene la última materia asociada al profesor
$ultima_materia = "No disponible";
$query_materia = "SELECT clave_materia FROM documentos WHERE id_profesor = ? ORDER BY anio DESC LIMIT 1";
$stmt_materia = $conn->prepare($query_materia); // Prepara la consulta
if ($stmt_materia) {
    $stmt_materia->bind_param("i", $id_profesor); // Vincula el ID del profesor
    $stmt_materia->execute(); // Ejecuta la consulta
    $result_materia = $stmt_materia->get_result(); // Obtiene el resultado
    if ($result_materia->num_rows > 0) {
        $ultima_materia = $result_materia->fetch_assoc()['clave_materia']; // Obtiene la clave de la materia
    }
    $stmt_materia->close(); // Cierra el statement
}

// Obtiene el grado de estudio más alto del profesor
$grado_estudio = "No disponible";
$query_grado = "SELECT tipo_documento AS nivel_formacion FROM documentos 
               WHERE id_profesor = ? 
               ORDER BY FIELD(tipo_documento, 'Doctorado', 'Maestría', 'Licenciatura') 
               LIMIT 1";
$stmt_grado = $conn->prepare($query_grado); // Prepara la consulta
if ($stmt_grado) {
    $stmt_grado->bind_param("i", $id_profesor); // Vincula el ID del profesor
    $stmt_grado->execute(); // Ejecuta la consulta
    $result_grado = $stmt_grado->get_result(); // Obtiene el resultado
    if ($result_grado->num_rows > 0) {
        $grado_estudio = $result_grado->fetch_assoc()['nivel_formacion']; // Obtiene el nivel de formación
    }
    $stmt_grado->close(); // Cierra el statement
}

// Obtiene el año de ingreso más antiguo
$anio_ingreso = "No disponible";
$query_ingreso = "SELECT MIN(anio) AS anio_ingreso FROM documentos WHERE id_profesor = ?";
$stmt_ingreso = $conn->prepare($query_ingreso); // Prepara la consulta
if ($stmt_ingreso) {
    $stmt_ingreso->bind_param("i", $id_profesor); // Vincula el ID del profesor
    $stmt_ingreso->execute(); // Ejecuta la consulta
    $result_ingreso = $stmt_ingreso->get_result(); // Obtiene el resultado
    if ($result_ingreso->num_rows > 0) {
        $anio_ingreso = $result_ingreso->fetch_assoc()['anio_ingreso']; // Obtiene el año de ingreso
    }
    $stmt_ingreso->close(); // Cierra el statement
}

// Cierra la conexión a la base de datos
$conn->close();
// Libera el búfer de salida
ob_end_flush();
?>

<!--CODIGO DE LA PAGINA WEB ----------------->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"> <!-- Define la codificación de caracteres -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Configura el diseño responsivo -->
    <title>Perfil del Profesor</title> <!-- Título de la página -->
    <link rel="stylesheet" href="../styles/styles_detailProfiles.css"> <!-- Enlace al archivo CSS -->
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
            <a href="add_user.php"><i class="fas fa-folder-open"></i>GESTION</a> <!-- Enlace a la gestión de usuarios/profesores -->
            <a href="all_Profiles.php"><i class="fas fa-user"></i> PERFILES</a> <!-- Enlace a los perfiles -->
        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a> <!-- Enlace para cerrar sesión -->
    </div>

    <main class="content">
        <div class="profile-container">
            <div class="profile-header">
                <!-- Muestra la imagen del profesor, con una imagen predeterminada si no está definida -->
                <img src="<?= htmlspecialchars($profesor['imagen'] ?: '../img/profesores/default.jpg') ?>" 
                     alt="Foto del Profesor">
                <!-- Muestra el nombre del profesor, escapado para prevenir XSS -->
                <h2><?= htmlspecialchars($profesor['nombre'] ?? 'Profesor no encontrado') ?></h2>
                <!-- Muestra el departamento del profesor -->
                <p class="department">Departamento de <?= htmlspecialchars($profesor['nombre_departamento'] ?? 'No asignado') ?></p>
            </div>

            <section class="info-section">
                <h3>Información del Profesor</h3>
                <div class="info-box">

                    <!-- Muestra el año de ingreso más antiguo -->
                    <p><strong>Año de ingreso:</strong> <?= htmlspecialchars($anio_ingreso) ?></p>
                </div>
            </section>
        </div>

        <aside class="documents">
            <h3>Documentos de Formación</h3>

            <!-- Categoría: Doctorado -->
            <div class="document-category">
                <button class="doc-header doctorado" onclick="toggleSection('Doctorado')">
                    Doctorado <i class="fas fa-chevron-down"></i> <!-- Botón para expandir/colapsar -->
                </button>
                <div class="doc-content" id="Doctorado" style="display: none;"> <!-- Contenido inicialmente oculto -->
                    <?php if (empty($documentos['Doctorado'])): ?>
                        <p class="no-docs">📄 Sin archivos</p> <!-- Mensaje si no hay documentos -->
                    <?php else: ?>
                        <?php foreach ($documentos['Doctorado'] as $doc): ?> <!-- Itera sobre los documentos -->
                            <div class="doc-item">
                                <div class="doc-info">
                                    <p><strong><?= htmlspecialchars($doc['titulo']) ?></strong></p> <!-- Título del documento -->
                                    <p><?= htmlspecialchars($doc['universidad']) ?></p> <!-- Universidad -->
                                    <p>Año: <?= htmlspecialchars($doc['anio']) ?></p> <!-- Año -->
                                    <button class="doc-btn" onclick="verDocumento('<?= htmlspecialchars($doc['archivo']) ?>')">👁 Ver</button> <!-- Botón para ver documento -->
                                    <a href="<?= htmlspecialchars($doc['archivo']) ?>" download class="doc-btn">⬇ Descargar</a> <!-- Enlace para descargar -->
                                    <?php if ($has_delete_permission): ?> <!-- Muestra botón de eliminar si el usuario tiene permisos -->
                                        <a href="../delete_document.php?id=<?= $doc['id_documento'] ?>" 
                                           class="doc-btn delete-btn" 
                                           onclick="return confirm('¿Estás seguro de que deseas eliminar este documento?');">
                                           🗑 Eliminar
                                        </a> <!-- Enlace para eliminar con confirmación -->
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Categoría: Maestría -->
            <div class="document-category">
                <button class="doc-header maestria" onclick="toggleSection('Maestría')">
                    Maestría <i class="fas fa-chevron-down"></i> <!-- Botón para expandir/colapsar -->
                </button>
                <div class="doc-content" id="Maestría" style="display: none;"> <!-- Contenido inicialmente oculto -->
                    <?php if (empty($documentos['Maestría'])): ?>
                        <p class="no-docs">📄 Sin archivos</p> <!-- Mensaje si no hay documentos -->
                    <?php else: ?>
                        <?php foreach ($documentos['Maestría'] as $doc): ?> <!-- Itera sobre los documentos -->
                            <div class="doc-item">
                                <div class="doc-info">
                                    <p><strong><?= htmlspecialchars($doc['titulo']) ?></strong></p> <!-- Título del documento -->
                                    <p><?= htmlspecialchars($doc['universidad']) ?></p> <!-- Universidad -->
                                    <p>Año: <?= htmlspecialchars($doc['anio']) ?></p> <!-- Año -->
                                    <button class="doc-btn" onclick="verDocumento('<?= htmlspecialchars($doc['archivo']) ?>')">👁 Ver</button> <!-- Botón para ver documento -->
                                    <a href="<?= htmlspecialchars($doc['archivo']) ?>" download class="doc-btn">⬇ Descargar</a> <!-- Enlace para descargar -->
                                    <?php if ($has_delete_permission): ?> <!-- Muestra botón de eliminar si el usuario tiene permisos -->
                                        <a href="../delete_document.php?id=<?= $doc['id_documento'] ?>" 
                                           class="doc-btn delete-btn" 
                                           onclick="return confirm('¿Estás seguro de que deseas eliminar este documento?');">
                                           🗑 Eliminar
                                        </a> <!-- Enlace para eliminar con confirmación -->
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Categoría: Licenciatura -->
            <div class="document-category">
                <button class="doc-header licenciatura" onclick="toggleSection('Licenciatura')">
                    Licenciatura <i class="fas fa-chevron-down"></i> <!-- Botón para expandir/colapsar -->
                </button>
                <div class="doc-content" id="Licenciatura" style="display: none;"> <!-- Contenido inicialmente oculto -->
                    <?php if (empty($documentos['Licenciatura'])): ?>
                        <p class="no-docs">📄 Sin archivos</p> <!-- Mensaje si no hay documentos -->
                    <?php else: ?>
                        <?php foreach ($documentos['Licenciatura'] as $doc): ?> <!-- Itera sobre los documentos -->
                            <div class="doc-item">
                                <div class="doc-info">
                                    <p><strong><?= htmlspecialchars($doc['titulo']) ?></strong></p> <!-- Título del documento -->
                                    <p><?= htmlspecialchars($doc['universidad']) ?></p> <!-- Universidad -->
                                    <p>Año: <?= htmlspecialchars($doc['anio']) ?></p> <!-- Año -->
                                    <button class="doc-btn" onclick="verDocumento('<?= htmlspecialchars($doc['archivo']) ?>')">👁 Ver</button> <!-- Botón para ver documento -->
                                    <a href="<?= htmlspecialchars($doc['archivo']) ?>" download class="doc-btn">⬇ Descargar</a> <!-- Enlace para descargar -->
                                    <?php if ($has_delete_permission): ?> <!-- Muestra botón de eliminar si el usuario tiene permisos -->
                                        <a href="../delete_document.php?id=<?= $doc['id_documento'] ?>" 
                                           class="doc-btn delete-btn" 
                                           onclick="return confirm('¿Estás seguro de que deseas eliminar este documento?');">
                                           🗑 Eliminar
                                        </a> <!-- Enlace para eliminar con confirmación -->
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Categoría: Cursos -->
            <div class="document-category">
                <button class="doc-header cursos" onclick="toggleSection('Cursos')">
                    Cursos, Diplomados, Certificados <i class="fas fa-chevron-down"></i> <!-- Botón para expandir/colapsar -->
                </button>
                <div class="doc-content" id="Cursos" style="display: none;"> <!-- Contenido inicialmente oculto -->
                    <?php if (empty($documentos['Cursos'])): ?>
                        <p class="no-docs">📄 Sin archivos</p> <!-- Mensaje si no hay documentos -->
                    <?php else: ?>
                        <?php foreach ($documentos['Cursos'] as $doc): ?> <!-- Itera sobre los documentos -->
                            <div class="doc-item">
                                <div class="doc-info">
                                    <p><strong><?= htmlspecialchars($doc['titulo']) ?></strong></p> <!-- Título del documento -->
                                    <p><?= htmlspecialchars($doc['universidad']) ?></p> <!-- Universidad -->
                                    <p>Año: <?= htmlspecialchars($doc['anio']) ?></p> <!-- Año -->
                                    <button class="doc-btn" onclick="verDocumento('<?= htmlspecialchars($doc['archivo']) ?>')">👁 Ver</button> <!-- Botón para ver documento -->
                                    <a href="<?= htmlspecialchars($doc['archivo']) ?>" download class="doc-btn">⬇ Descargar</a> <!-- Enlace para descargar -->
                                    <?php if ($has_delete_permission): ?> <!-- Muestra botón de eliminar si el usuario tiene permisos -->
                                        <a href="../delete_document.php?id=<?= $doc['id_documento'] ?>" 
                                           class="doc-btn delete-btn" 
                                           onclick="return confirm('¿Estás seguro de que deseas eliminar este documento?');">
                                           🗑 Eliminar
                                        </a> <!-- Enlace para eliminar con confirmación -->
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </main>

    <script>
        // Función para expandir o colapsar secciones de documentos
        function toggleSection(id) {
            let content = document.getElementById(id); // Obtiene el elemento por ID
            content.style.display = (content.style.display === "none" || content.style.display === "") ? "flex" : "none"; // Alterna entre mostrar y ocultar
        }

        // Función para abrir un documento en una nueva pestaña
        function verDocumento(archivo) {
            window.open(archivo, '_blank'); // Abre el archivo en una nueva ventana
        }

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