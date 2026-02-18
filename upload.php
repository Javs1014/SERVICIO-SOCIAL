<?php
// 1. Evitamos que cualquier error de PHP o espacio en blanco salga antes del JSON
ob_start();

// Incluye el archivo de conexión
include 'db.php';

// ==============================================
// CONFIGURACIÓN DE LA SUBIDA DE ARCHIVOS
// ==============================================

// __DIR__ es la carpeta actual. Si 'uploads' está afuera de la carpeta del script:
$uploadDir = dirname(__DIR__) . '/uploads/'; 

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$response = [
    'status' => 'error',
    'message' => 'Error desconocido'
];

// ==============================================
// VALIDACIÓN DE CAMPOS REQUERIDOS
// ==============================================
$requiredFields = ['id_profesor', 'tipo_documento'];

foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        $response['message'] = "El campo $field es requerido";
        ob_clean(); 
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// ==============================================
// SANITIZACIÓN DE DATOS
// ==============================================
$id_profesor = intval($_POST['id_profesor']);
$tipo_documento = htmlspecialchars($_POST['tipo_documento'], ENT_QUOTES, 'UTF-8');

// Lógica para "OTROS"
if ($tipo_documento === 'otros' && !empty($_POST['otros_tipo'])) {
    $tipo_documento = 'otros_' . htmlspecialchars($_POST['otros_tipo'], ENT_QUOTES, 'UTF-8');
}

// Lógica para "HORARIO" (Mantenemos tu lógica específica)
if ($tipo_documento === 'horario' && !empty($_POST['horario_tipo'])) {
    $tipo_documento = 'horario_' . htmlspecialchars($_POST['horario_tipo'], ENT_QUOTES, 'UTF-8');
}

// Sanitización de campos académicos
$periodo = !empty($_POST['periodo']) ? htmlspecialchars($_POST['periodo'], ENT_QUOTES, 'UTF-8') : null;
$anio = !empty($_POST['anio']) ? intval($_POST['anio']) : null;
$clave_materia = !empty($_POST['clave_materia']) ? htmlspecialchars($_POST['clave_materia'], ENT_QUOTES, 'UTF-8') : null;
$grupo = !empty($_POST['grupo']) ? htmlspecialchars($_POST['grupo'], ENT_QUOTES, 'UTF-8') : null;

// ==============================================
// PROCESAMIENTO DEL ARCHIVO
// ==============================================
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    
    $allowedTypes = ['image/jpeg', 'image/png'];
    $fileType = mime_content_type($_FILES['imagen']['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        $response['message'] = 'Tipo de archivo no permitido. Use JPG o PNG.';
        ob_clean();
        echo json_encode($response);
        exit;
    }

    // Máximo 5MB como respaldo del servidor
    if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
        $response['message'] = 'El archivo supera el límite de 5MB.';
        ob_clean();
        echo json_encode($response);
        exit;
    }

    // Obtener nombre del profesor para el nombre del archivo
    $stmt = $conn->prepare("SELECT nombre FROM profesores WHERE id_profesor = ?");
    $stmt->bind_param("i", $id_profesor);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $nombreProfesor = preg_replace('/\s+/', '_', $row['nombre']);
    } else {
        $nombreProfesor = 'Desconocido';
    }
    $stmt->close();

    // Generar nombre descriptivo único
    $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
    $nombreImagen = sprintf("%s_%s_%s_%s_%s_%s.%s",
        $tipo_documento,
        $periodo ?? 'NA',
        $anio ?? '0000',
        $nombreProfesor,
        $clave_materia ?? 'NA',
        $grupo ?? 'NA',
        $extension
    );
    
    $rutaImagen = $uploadDir . $nombreImagen;

    // Intentar mover el archivo y guardar en BD
    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaImagen)) {
        
        $stmt = $conn->prepare("INSERT INTO documentos 
            (id_profesor, tipo_documento, periodo, anio, clave_materia, grupo, imagen) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ississs", 
            $id_profesor, 
            $tipo_documento, 
            $periodo, 
            $anio, 
            $clave_materia, 
            $grupo, 
            $nombreImagen
        );
        
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = '✅ Documento cargado correctamente.';
        } else {
            $response['message'] = '❌ Error DB: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = '❌ Error al mover el archivo. Revisa permisos de carpeta.';
    }
} else {
    $response['message'] = '⚠️ No se recibió archivo o hay error de subida (Código: ' . ($_FILES['imagen']['error'] ?? 'N/A') . ')';
}

$conn->close();

// Limpiamos cualquier salida accidental (Warnings, Echos perdidos) y enviamos el JSON
ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit;