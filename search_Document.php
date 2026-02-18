<?php
// Inicia la sesión para acceder a las variables de sesión
session_start();
// Incluye el archivo de conexión a la base de datos
require_once './db.php';

/**
 * Función auxiliar para obtener parámetros GET de manera segura
 * @param string $key - Nombre del parámetro GET a buscar
 * @param mixed $default - Valor por defecto si el parámetro no está definido
 * @return mixed - Retorna el valor del parámetro o el valor por defecto
 */
function get_param($key, $default = 'Todos') {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
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

// Resto del código PHP (filtros, consulta SQL, etc.) permanece igual
$filtros = [
    'anio' => get_param('yearFilter'),
    'periodo' => get_param('periodFilter'),
    'tipo' => get_param('docTypeFilter'),
    'clave' => get_param('claveFilter'),
    'grupo' => get_param('groupFilter'),
    'id_profesor' => get_param('id_profesor', '')
];

$where = [];
$params = [];
$types = '';

if ($filtros['anio'] !== 'Todos') {
    $where[] = "d.anio = ?";
    $params[] = $filtros['anio'];
    $types .= 's';
}

if ($filtros['periodo'] !== 'Todos') {
    $where[] = "d.periodo = ?";
    $params[] = $filtros['periodo'];
    $types .= 's';
}

if ($filtros['tipo'] !== 'Todos') {
    $where[] = "d.tipo_documento = ?";
    $params[] = $filtros['tipo'];
    $types .= 's';
}

if ($filtros['clave'] !== 'Todos') {
    $where[] = "d.clave_materia = ?";
    $params[] = $filtros['clave'];
    $types .= 's';
}

if ($filtros['grupo'] !== 'Todos') {
    $where[] = "d.grupo = ?";
    $params[] = $filtros['grupo'];
    $types .= 's';
}

if (!empty($filtros['id_profesor'])) {
    $where[] = "d.id_profesor = ?";
    $params[] = $filtros['id_profesor'];
    $types .= 'i';
}

$sql = "SELECT d.*, p.nombre AS profesor
        FROM documentos d
        JOIN profesores p ON d.id_profesor = p.id_profesor";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$stmt = $conn->prepare($sql);
if ($types && $stmt) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$documentos = [];
while ($row = $result->fetch_assoc()) {
    $documentos[] = [
        'id' => $row['id_documento'],
        'profesor' => $row['profesor'],
        'tipo' => $row['tipo_documento'],
        'periodo' => $row['periodo'],
        'clave' => $row['clave_materia'],
        'grupo' => $row['grupo'],
        'anio' => $row['anio'],
        'imagen' => '/Uploads/' . $row['imagen']
    ];
}
$stmt->close();

function obtenerValoresUnicos($conn, $campo) {
    $valores = [];
    $sql = "SELECT DISTINCT `$campo` FROM documentos 
            WHERE `$campo` IS NOT NULL AND `$campo` != '' 
            ORDER BY `$campo`";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $valores[] = $row[$campo];
        }
        $stmt->close();
    }
    return $valores;
}

$anios = obtenerValoresUnicos($conn, 'anio');
$grupos = obtenerValoresUnicos($conn, 'grupo');
$claves = obtenerValoresUnicos($conn, 'clave_materia');
$periodos = obtenerValoresUnicos($conn, 'periodo');
$tipos = obtenerValoresUnicos($conn, 'tipo_documento');

$profesores = [];
$stmt = $conn->prepare("SELECT id_profesor, nombre FROM profesores ORDER BY nombre");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $profesores[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador de Documentos</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../styles/styles_search.css">
    <link rel="stylesheet" href="../styles/normalize.css">
</head>
<body>
    <button id="sidebar-toggle" class="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar">
        <div>
            <h2>DRWSC-B</h2>
            <p>Desarrollo de repositorio web de <br>sistemas y computación beta</p>
        </div>
        <div class="menu">
            <a href="index.php"><i class="fas fa-home"></i> INICIO</a>
            <a href="search_Document.php"><i class="fas fa-search"></i> BUSCAR</a>
            <a href="upload_form.php"><i class="fas fa-upload"></i> SUBIR</a>
            <a href="add_user.php"><i class="fas fa-folder-open"></i>GESTION</a>
            <a href="all_Profiles.php"><i class="fas fa-user"></i> PERFILES</a>
        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a>
    </div>

    <div class="content">
    <?php if ($is_authorized): ?>
        <div class="container-wrapper">
            <div class="container">
                <h2>Buscador de Documentos</h2>
                <div class="search-container">
                    <div class="filter-group">
                        <select id="docTypeFilter" onchange="toggleOtrosSubtype()">
                            <option value="Todos">Tipo</option>
                            <option value="acta" <?= ($filtros['tipo'] == 'acta') ? 'selected' : '' ?>>Acta</option>
                            <option value="horario" <?= ($filtros['tipo'] == 'horario') ? 'selected' : '' ?>>Horario</option>
                            <option value="otros" <?= strpos($filtros['tipo'], 'otros_') === 0 ? 'selected' : '' ?>>Otros</option>
                        </select>
                        <select id="otrosSubtypeFilter" style="<?= strpos($filtros['tipo'], 'otros_') === 0 ? '' : 'display: none;' ?>">
                            <option value="otros_licenciatura" <?= ($filtros['tipo'] == 'otros_licenciatura') ? 'selected' : '' ?>>Licenciatura</option>
                            <option value="otros_maestria" <?= ($filtros['tipo'] == 'otros_maestria') ? 'selected' : '' ?>>Maestría</option>
                            <option value="otros_doctorado" <?= ($filtros['tipo'] == 'otros_doctorado') ? 'selected' : '' ?>>Doctorado</option>
                            <option value="otros_cursos" <?= ($filtros['tipo'] == 'otros_cursos') ? 'selected' : '' ?>>Cursos</option>
                            <option value="otros_otros" <?= ($filtros['tipo'] == 'otros_otros') ? 'selected' : '' ?>>Otros</option>
                        </select>
                        <select id="yearFilter" class="<?= strpos($filtros['tipo'], 'otros_') === 0 ? 'filter-hidden' : '' ?>">
                            <option value="Todos">Año</option>
                            <?php foreach ($anios as $a): ?>
                                <option value="<?= $a ?>" <?= ($filtros['anio'] == $a) ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="periodFilter" class="<?= strpos($filtros['tipo'], 'otros_') === 0 ? 'filter-hidden' : '' ?>">
                            <option value="Todos">Periodo</option>
                            <?php foreach ($periodos as $p): ?>
                                <option value="<?= $p ?>" <?= ($filtros['periodo'] == $p) ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="claveFilter" class="<?= strpos($filtros['tipo'], 'otros_') === 0 ? 'filter-hidden' : '' ?>">
                            <option value="Todos">Clave</option>
                            <?php foreach ($claves as $c): ?>
                                <option value="<?= $c ?>" <?= ($filtros['clave'] == $c) ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="groupFilter" class="<?= strpos($filtros['tipo'], 'otros_') === 0 ? 'filter-hidden' : '' ?>">
                            <option value="Todos">Grupo</option>
                            <?php foreach ($grupos as $g): ?>
                                <option value="<?= $g ?>" <?= ($filtros['grupo'] == $g) ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="id_profesor">
                            <option value="">Profesor</option>
                            <?php foreach ($profesores as $prof): ?>
                                <option value="<?= $prof['id_profesor'] ?>" <?= ($filtros['id_profesor'] == $prof['id_profesor']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prof['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="search-btn-container">
                        <button class="search-btn" onclick="buscarDocumentos()"><i class="fas fa-search"></i> Buscar</button>
                    </div>
                </div>
            </div>
            <div class="documents">
                <?php if (count($documentos)): ?>
                    <?php foreach ($documentos as $doc): ?>
                        <div class="doc-card">
                            <a href="document_detail.php?id=<?= $doc['id'] ?>">
                                <img src="<?= htmlspecialchars($doc['imagen']) ?>" alt="Documento">
                                <h4><?= htmlspecialchars($doc['profesor']) ?></h4>
                                <p><?= htmlspecialchars("{$doc['tipo']} | {$doc['periodo']} | {$doc['anio']}") ?></p>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No se encontraron documentos con los filtros seleccionados.</p>
                <?php endif; ?>
            </div>
            
        </div>
    <?php else: ?>
        <div class="error-message">
            <h2>No tienes permisos para acceder a esta página.</h2>
            <p>Por favor, inicia sesión con una cuenta autorizada.</p>
            <a href="login.php">Iniciar Sesión</a>
        </div>
    <?php endif; ?>
    
</div>

    <script>
        function toggleOtrosSubtype() {
            const docType = document.getElementById('docTypeFilter').value;
            const otrosSubtype = document.getElementById('otrosSubtypeFilter');
            const normalFilters = [
                document.getElementById('yearFilter'),
                document.getElementById('periodFilter'),
                document.getElementById('claveFilter'),
                document.getElementById('groupFilter')
            ];
            if (docType === 'otros') {
                otrosSubtype.style.display = 'inline-block';
                normalFilters.forEach(filter => {
                    filter.classList.add('filter-hidden');
                });
            } else {
                otrosSubtype.style.display = 'none';
                normalFilters.forEach(filter => {
                    filter.classList.remove('filter-hidden');
                });
            }
        }

        function buscarDocumentos() {
            const params = new URLSearchParams();
            const docType = document.getElementById('docTypeFilter').value;
            let tipoValue = docType;
            if (docType === 'otros') {
                tipoValue = document.getElementById('otrosSubtypeFilter').value;
            }
            if (tipoValue !== 'Todos') {
                params.append('docTypeFilter', tipoValue);
            }
            if (docType !== 'otros') {
                ['yearFilter', 'periodFilter', 'claveFilter', 'groupFilter'].forEach(id => {
                    const value = document.getElementById(id).value;
                    if (value && value !== 'Todos') {
                        params.append(id, value);
                    }
                });
            }
            const profesorValue = document.getElementById('id_profesor').value;
            if (profesorValue) {
                params.append('id_profesor', profesorValue);
            }
            window.location.href = 'search_Document.php?' + params.toString();
        }

        const sidebarToggle = document.getElementById("sidebar-toggle");
        const sidebar = document.querySelector(".sidebar");
        const content = document.querySelector(".content");

        if (sidebarToggle) {
            sidebarToggle.addEventListener("click", function () {
                sidebar.classList.toggle("collapsed");
                content.classList.toggle("collapsed");
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const docTypeFilter = document.getElementById('docTypeFilter');
            if (docTypeFilter.value === 'otros') {
                toggleOtrosSubtype();
            }
            const tipoActual = '<?= $filtros['tipo'] ?>';
            if (tipoActual.startsWith('otros_')) {
                const otrosSubtype = document.getElementById('otrosSubtypeFilter');
                otrosSubtype.value = tipoActual;
            }
        });
 
    </script>
</body>
</html>