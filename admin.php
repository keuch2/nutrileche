<?php
session_start();

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'c2780418_nutri';
$username = 'c2780418_nutri';
$password = '41keDUlena';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, false); // Disable autocommit
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Credenciales de administrador (hardcoded para simplicidad)
$admin_username = 'admin';
$admin_password = 'capainlac2025';

// Variable para almacenar mensajes de feedback
$feedback_message = '';
$feedback_type = ''; // 'success' o 'error'
$debug_info = []; // Array para almacenar información de depuración

// Función para obtener el ícono según el tipo de archivo
function getFileIcon($type) {
    if ($type === 'proyecto' || $type === 'redacciones') {
        return 'fas fa-file-alt';
    } elseif ($type === 'fotos') {
        return 'fas fa-image';
    } elseif ($type === 'videos') {
        return 'fas fa-video';
    }
    return 'fas fa-file';
}

// Función para normalizar nombres de departamentos
function normalizeDepartamento($departamento) {
    $departamento = strtolower($departamento);
    $departamento = str_replace(' ', '', $departamento);
    $departamento = str_replace('ó', 'o', $departamento);
    return $departamento;
}

// Función para mostrar nombres de departamentos en formato legible
function displayDepartamento($departamento) {
    $display_map = [
        'altoparaguay' => 'Alto Paraguay',
        'boqueron' => 'Boquerón',
        'misiones' => 'Misiones',
        'presidentehayes' => 'Presidente Hayes'
    ];
    
    // Si el departamento está en el mapping, usamos el nombre predefinido
    if (isset($display_map[$departamento])) {
        return $display_map[$departamento];
    }
    
    // Si no está en el mapping, formateamos el nombre automáticamente
    $departamento = str_replace('_', ' ', $departamento);
    $departamento = ucwords($departamento);
    
    // Corregimos casos específicos como 'De La' -> 'de la'
    $departamento = str_replace(' De La ', ' de la ', $departamento);
    $departamento = str_replace(' Del ', ' del ', $departamento);
    $departamento = str_replace(' Y ', ' y ', $departamento);
    
    return $departamento;
}

// Esta definición se movió al principio del archivo para evitar errores

// Manejar login de administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_DEFAULT));
    $password = trim(filter_input(INPUT_POST, 'password', FILTER_DEFAULT));

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $feedback_message = 'Usuario o contraseña incorrectos';
        $feedback_type = 'error';
    }
}

// Manejar logout de administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_logout') {
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Manejar descarga de ZIP de usuario individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_user' && isset($_SESSION['admin'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if (!$user_id) {
        die(json_encode(['success' => false, 'message' => 'ID de usuario inválido']));
    }
    
    // Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die(json_encode(['success' => false, 'message' => 'Usuario no encontrado']));
    }
    
    // Obtener evidencias del usuario
    $stmt = $pdo->prepare("SELECT * FROM evidences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $evidences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $zip_filename = 'usuario_' . $user_id . '_' . preg_replace('/[^A-Za-z0-9]/', '_', $user['nombre']) . '_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die(json_encode(['success' => false, 'message' => 'No se pudo crear el archivo ZIP']));
    }
    
    // Añadir información del usuario
    $user_info = "INFORMACIÓN DEL USUARIO\n";
    $user_info .= "========================\n\n";
    $user_info .= "Nombre: " . $user['nombre'] . "\n";
    $user_info .= "Email: " . $user['email'] . "\n";
    $user_info .= "Teléfono: " . $user['telefono'] . "\n";
    $user_info .= "Departamento: " . displayDepartamento($user['departamento']) . "\n";
    $user_info .= "Ciudad: " . $user['ciudad'] . "\n";
    $user_info .= "Institución: " . $user['institucion'] . "\n";
    $user_info .= "Sección: " . ($user['seccion'] ?: 'N/A') . "\n";
    $user_info .= "Turno: " . ($user['turno'] ?: 'N/A') . "\n";
    $user_info .= "Modalidad: " . $user['modalidad'] . "\n";
             $user_info .= "Fecha de registro: " . ($user['created_at'] ?? 'No disponible') . "\n\n";
    
    $zip->addFromString('info_usuario.txt', $user_info);
    
    // Añadir archivos de evidencias organizados por tipo
    $evidence_count = 0;
    foreach ($evidences as $evidence) {
        if (file_exists($evidence['file_path'])) {
            $folder = ucfirst($evidence['type']) . '/';
            $zip->addFile($evidence['file_path'], $folder . $evidence['file_name']);
            $evidence_count++;
        }
    }
    
    $zip->close();
    
    if ($evidence_count === 0 && empty($evidences)) {
        unlink($zip_path);
        die(json_encode(['success' => false, 'message' => 'El usuario no tiene evidencias para descargar']));
    }
    
    // Enviar archivo ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    readfile($zip_path);
    unlink($zip_path);
    exit;
}

// Manejar descarga de ZIP por departamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_department' && isset($_SESSION['admin'])) {
    $department = filter_input(INPUT_POST, 'department', FILTER_DEFAULT);
    $department = normalizeDepartamento($department);
    
    if (empty($department)) {
        die(json_encode(['success' => false, 'message' => 'Departamento inválido']));
    }
    
    // Obtener usuarios del departamento
    $stmt = $pdo->prepare("SELECT * FROM users WHERE departamento = ?");
    $stmt->execute([$department]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        die(json_encode(['success' => false, 'message' => 'No hay usuarios en este departamento']));
    }
    
    $zip_filename = 'departamento_' . $department . '_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die(json_encode(['success' => false, 'message' => 'No se pudo crear el archivo ZIP']));
    }
    
    // Información general del departamento
    $dept_info = "INFORMACIÓN DEL DEPARTAMENTO: " . displayDepartamento($department) . "\n";
    $dept_info .= "======================================================\n\n";
    $dept_info .= "Total de usuarios: " . count($users) . "\n";
    $dept_info .= "Fecha de descarga: " . date('d/m/Y H:i:s') . "\n\n";
    $dept_info .= "LISTADO DE USUARIOS:\n";
    $dept_info .= "===================\n\n";
    
    foreach ($users as $user) {
        $dept_info .= "- " . $user['nombre'] . " (ID: " . $user['id'] . ")\n";
        $dept_info .= "  Email: " . $user['email'] . "\n";
        $dept_info .= "  Institución: " . $user['institucion'] . "\n";
        $dept_info .= "  Ciudad: " . $user['ciudad'] . "\n\n";
    }
    
    $zip->addFromString('info_departamento.txt', $dept_info);
    
    // Procesar cada usuario
    foreach ($users as $user) {
        // Crear carpeta para el usuario
        $user_folder = preg_replace('/[^A-Za-z0-9]/', '_', $user['nombre']) . '_ID' . $user['id'] . '/';
        
        // Información individual del usuario
        $user_info = "INFORMACIÓN DEL USUARIO\n";
        $user_info .= "========================\n\n";
        $user_info .= "Nombre: " . $user['nombre'] . "\n";
        $user_info .= "Email: " . $user['email'] . "\n";
        $user_info .= "Teléfono: " . $user['telefono'] . "\n";
        $user_info .= "Ciudad: " . $user['ciudad'] . "\n";
        $user_info .= "Institución: " . $user['institucion'] . "\n";
        $user_info .= "Sección: " . ($user['seccion'] ?: 'N/A') . "\n";
        $user_info .= "Turno: " . ($user['turno'] ?: 'N/A') . "\n";
        $user_info .= "Modalidad: " . $user['modalidad'] . "\n";
        $user_info .= "Fecha de registro: " . ($user['created_at'] ?? 'No disponible') . "\n\n";
        
        $zip->addFromString($user_folder . 'info_usuario.txt', $user_info);
        
        // Obtener evidencias del usuario
        $stmt = $pdo->prepare("SELECT * FROM evidences WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $evidences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Añadir archivos de evidencias
        foreach ($evidences as $evidence) {
            if (file_exists($evidence['file_path'])) {
                $evidence_folder = $user_folder . ucfirst($evidence['type']) . '/';
                $zip->addFile($evidence['file_path'], $evidence_folder . $evidence['file_name']);
            }
        }
    }
    
    $zip->close();
    
    // Enviar archivo ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    readfile($zip_path);
    unlink($zip_path);
    exit;
}

// Manejar eliminación de archivo individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file' && isset($_SESSION['admin'])) {
    $file_path = filter_input(INPUT_POST, 'file_path', FILTER_DEFAULT);
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if (!$file_path || !$user_id) {
        die(json_encode(['success' => false, 'message' => 'Datos inválidos']));
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verificar que el archivo existe en la base de datos
        $stmt = $pdo->prepare("SELECT * FROM evidences WHERE file_path = ? AND user_id = ?");
        $stmt->execute([$file_path, $user_id]);
        $evidence = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evidence) {
            $pdo->rollBack();
            die(json_encode(['success' => false, 'message' => 'Archivo no encontrado en la base de datos']));
        }
        
        // Eliminar de la base de datos
        $stmt = $pdo->prepare("DELETE FROM evidences WHERE file_path = ? AND user_id = ?");
        $stmt->execute([$file_path, $user_id]);
        
        // Eliminar archivo físico si existe
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $pdo->commit();
        die(json_encode(['success' => true, 'message' => 'Archivo eliminado exitosamente']));
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die(json_encode(['success' => false, 'message' => 'Error al eliminar archivo: ' . $e->getMessage()]));
    }
}

// Manejar eliminación de usuario completo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user' && isset($_SESSION['admin'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if (!$user_id) {
        die(json_encode(['success' => false, 'message' => 'ID de usuario inválido']));
    }
    
    try {
        $pdo->beginTransaction();
        
        // Obtener información del usuario
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $pdo->rollBack();
            die(json_encode(['success' => false, 'message' => 'Usuario no encontrado']));
        }
        
        // Obtener todos los archivos del usuario
        $stmt = $pdo->prepare("SELECT file_path FROM evidences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Eliminar archivos físicos
        foreach ($files as $file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Eliminar carpeta del usuario si está vacía
        if (!empty($files)) {
            $user_folder = dirname($files[0]);
            if (is_dir($user_folder) && count(scandir($user_folder)) <= 2) { // Solo . y ..
                rmdir($user_folder);
            }
        }
        
        // Eliminar evidencias de la base de datos
        $stmt = $pdo->prepare("DELETE FROM evidences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Eliminar usuario de la base de datos
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        die(json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente']));
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die(json_encode(['success' => false, 'message' => 'Error al eliminar usuario: ' . $e->getMessage()]));
    }
}

// Manejar adición de un nuevo departamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_department') {
    if (!isset($_SESSION['admin'])) {
        $feedback_message = 'Debes iniciar sesión como administrador';
        $feedback_type = 'error';
    } else {
        $new_departamento = trim(filter_input(INPUT_POST, 'new_departamento', FILTER_DEFAULT));
        $new_departamento = normalizeDepartamento($new_departamento);

        if (empty($new_departamento)) {
            $feedback_message = 'El nombre del departamento no puede estar vacío';
            $feedback_type = 'error';
        } else {
            try {
                // Start a transaction
                $pdo->beginTransaction();
                $debug_info[] = "Transacción iniciada para añadir departamento";

                // Check if the department already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM department_permissions WHERE departamento = ?");
                $stmt->execute([$new_departamento]);
                if ($stmt->fetchColumn() > 0) {
                    $feedback_message = 'El departamento ya existe';
                    $feedback_type = 'error';
                    $pdo->rollBack();
                } else {
                    // Insert the new department with default permissions
                    $stmt = $pdo->prepare("INSERT INTO department_permissions (departamento, can_create_account, can_edit_submission) VALUES (?, 0, 0)");
                    $stmt->execute([$new_departamento]);
                    $debug_info[] = "Departamento '$new_departamento' añadido con éxito";

                    // Commit the transaction
                    $pdo->commit();
                    $debug_info[] = "Transacción confirmada para añadir departamento";

                    $feedback_message = "Departamento '" . displayDepartamento($new_departamento) . "' añadido con éxito";
                    $feedback_type = 'success';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $feedback_message = 'Error al añadir el departamento: ' . $e->getMessage();
                $feedback_type = 'error';
                $debug_info[] = "Excepción PDO: " . $e->getMessage();
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback_message = 'Error inesperado: ' . $e->getMessage();
                $feedback_type = 'error';
                $debug_info[] = "Excepción general: " . $e->getMessage();
            }
        }
    }
}

// Manejar actualización de permisos de departamentos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_permissions') {
    if (!isset($_SESSION['admin'])) {
        $feedback_message = 'Debes iniciar sesión como administrador';
        $feedback_type = 'error';
    } else {
        $debug_info[] = "Método de solicitud: " . $_SERVER['REQUEST_METHOD'];
        $debug_info[] = "Datos recibidos: " . json_encode($_POST);

        $departamentos = $_POST['departamentos'] ?? [];
        $can_create_account = $_POST['can_create_account'] ?? [];
        $can_edit_submission = $_POST['can_edit_submission'] ?? [];

        $debug_info[] = "Departamentos: " . json_encode($departamentos);
        $debug_info[] = "Can Create Account (raw): " . json_encode($can_create_account);
        $debug_info[] = "Can Edit Submission (raw): " . json_encode($can_edit_submission);

        if (empty($departamentos)) {
            $feedback_message = 'No se recibieron departamentos para actualizar';
            $feedback_type = 'error';
        } else {
            try {
                // Start a transaction
                $pdo->beginTransaction();
                $debug_info[] = "Transacción iniciada";

                // Fetch existing departamentos from the database for comparison
                $stmt = $pdo->query("SELECT departamento FROM department_permissions");
                $existing_departamentos = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $debug_info[] = "Departamentos existentes en la BD: " . json_encode($existing_departamentos);

                // Fetch current state of the table
                $stmt = $pdo->query("SELECT * FROM department_permissions");
                $before_update = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $debug_info[] = "Estado de la tabla antes de la actualización: " . json_encode($before_update);

                $updated_count = 0;
                $failed_updates = [];
                foreach ($departamentos as $index => $departamento) {
                    $departamento = normalizeDepartamento($departamento);
                    if (empty($departamento)) {
                        $debug_info[] = "Departamento vacío en índice $index";
                        continue;
                    }

                    // Check the actual value of the checkbox, not just the existence of the key
                    $create = isset($can_create_account[$index]) && $can_create_account[$index] == '1' ? 1 : 0;
                    $edit = isset($can_edit_submission[$index]) && $can_edit_submission[$index] == '1' ? 1 : 0;

                    $debug_info[] = "Procesando '$departamento': can_create_account=$create, can_edit_submission=$edit";

                    // Check if the row exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM department_permissions WHERE departamento = ?");
                    $stmt->execute([$departamento]);
                    $row_exists = $stmt->fetchColumn() > 0;
                    $debug_info[] = "Fila existe para '$departamento': " . ($row_exists ? 'Sí' : 'No');

                    if ($row_exists) {
                        // Update the existing row
                        $stmt = $pdo->prepare("UPDATE department_permissions SET can_create_account = ?, can_edit_submission = ? WHERE departamento = ?");
                        $stmt->execute([$create, $edit, $departamento]);
                        $debug_info[] = "Consulta UPDATE ejecutada para '$departamento': SET can_create_account=$create, can_edit_submission=$edit";

                        // Verify the update by checking the row
                        $stmt = $pdo->prepare("SELECT can_create_account, can_edit_submission FROM department_permissions WHERE departamento = ?");
                        $stmt->execute([$departamento]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && (int)$row['can_create_account'] === $create && (int)$row['can_edit_submission'] === $edit) {
                            $updated_count++;
                            $debug_info[] = "Actualización verificada para '$departamento': Éxito";
                        } else {
                            $failed_updates[] = displayDepartamento($departamento);
                            $debug_info[] = "Fallo en la verificación de la actualización para '$departamento': Esperado can_create_account=$create, can_edit_submission=$edit, Obtenido " . json_encode($row);
                        }
                    } else {
                        // Insert a new row
                        $stmt = $pdo->prepare("INSERT INTO department_permissions (departamento, can_create_account, can_edit_submission) VALUES (?, ?, ?)");
                        try {
                            $stmt->execute([$departamento, $create, $edit]);
                            $updated_count++;
                            $debug_info[] = "INSERT exitoso para '$departamento'";
                        } catch (PDOException $e) {
                            $debug_info[] = "Fallo en el INSERT para '$departamento': " . $e->getMessage();
                            $failed_updates[] = displayDepartamento($departamento);
                        }
                    }
                }

                // Fetch the state of the table after the update
                $stmt = $pdo->query("SELECT * FROM department_permissions");
                $after_update = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $debug_info[] = "Estado de la tabla después de la actualización: " . json_encode($after_update);

                // Commit the transaction
                $pdo->commit();
                $debug_info[] = "Transacción confirmada";

                $debug_info[] = "Total de actualizaciones/inserciones: $updated_count";

                if ($updated_count === 0) {
                    $feedback_message = 'No se actualizaron los permisos. Departamentos que fallaron: ' . implode(', ', $failed_updates);
                    $feedback_type = 'error';
                } else {
                    $feedback_message = "Permisos actualizados con éxito. Filas afectadas: $updated_count";
                    $feedback_type = 'success';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $feedback_message = 'Error al actualizar los permisos: ' . $e->getMessage();
                $feedback_type = 'error';
                $debug_info[] = "Excepción PDO: " . $e->getMessage();
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback_message = 'Error inesperado: ' . $e->getMessage();
                $feedback_type = 'error';
                $debug_info[] = "Excepción general: " . $e->getMessage();
            }
        }
    }
}

// Obtener todas las evidencias si el administrador está autenticado
$evidences_by_user = [];
$department_permissions = [];
if (isset($_SESSION['admin'])) {
    // Verificar qué columnas de timestamp existen (una sola vez)
    $stmt = $pdo->prepare("SHOW COLUMNS FROM evidences LIKE 'created_at'");
    $stmt->execute();
    $evidence_has_created_at = $stmt->fetchColumn() !== false;
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'created_at'");
    $stmt->execute();
    $user_has_created_at = $stmt->fetchColumn() !== false;
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'updated_at'");
    $stmt->execute();
    $user_has_updated_at = $stmt->fetchColumn() !== false;

    // Obtener todos los usuarios
    $stmt = $pdo->prepare("SELECT * FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada usuario, obtener sus evidencias y fecha de última actualización
    foreach ($users as $user) {
        $stmt = $pdo->prepare("SELECT type, file_name, file_path FROM evidences WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $evidences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener la fecha de la última evidencia subida si la columna existe
        $evidence_updated = null;
        if ($evidence_has_created_at) {
            $stmt = $pdo->prepare("SELECT MAX(created_at) as last_evidence_date FROM evidences WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $last_evidence = $stmt->fetch(PDO::FETCH_ASSOC);
            $evidence_updated = $last_evidence['last_evidence_date'] ?? null;
        }
        
        // Determinar la fecha de última actualización
        $user_updated = null;
        if ($user_has_updated_at && isset($user['updated_at'])) {
            $user_updated = $user['updated_at'];
        } elseif ($user_has_created_at && isset($user['created_at'])) {
            $user_updated = $user['created_at'];
        }
        
        $last_update = $user_updated;
        if ($evidence_updated && $user_updated && $evidence_updated > $user_updated) {
            $last_update = $evidence_updated;
        } elseif ($evidence_updated && !$user_updated) {
            $last_update = $evidence_updated;
        }

        $evidences_by_user[] = [
            'user' => $user,
            'evidences' => $evidences,
            'last_update' => $last_update
        ];
    }

    // Obtener permisos de departamentos
    $stmt = $pdo->prepare("SELECT departamento, can_create_account, can_edit_submission FROM department_permissions");
    $stmt->execute();
    $department_permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Reset autocommit
$pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Concurso Nutrileche 2025</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f4f4f4;
        }
        .container {
            margin-top: 20px;
        }
        .submission-section {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .submission-section h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .file-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .file-item {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 5px 10px;
        }
        .file-item i {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        .file-item a {
            color: #007bff;
            text-decoration: none;
        }
        .file-item a:hover {
            text-decoration: underline;
        }
        .permissions-section {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .permissions-section h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .add-department-section {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .add-department-section h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .feedback-message {
            margin-bottom: 20px;
        }
        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            white-space: pre-wrap;
        }
        .filters-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
        }
        .stats-section {
            margin-bottom: 20px;
        }
        .stat-card {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
            margin: 0;
        }
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
        .user-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .department-badge {
            margin-left: auto;
        }
        .download-user-btn {
            white-space: nowrap;
        }
        .user-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .user-info p {
            margin-bottom: 8px;
        }
        .user-info i {
            width: 16px;
            margin-right: 8px;
        }
        .evidences-section {
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
        }
        .evidence-categories {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        .evidence-category {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
        }
        .evidence-category h5 {
            margin-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        .evidence-category .file-list {
            margin-top: 10px;
        }
        .evidence-category .file-item {
            margin-bottom: 8px;
            padding: 5px;
            background-color: #fff;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .evidence-category .file-item .file-content {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }
        .delete-file-btn {
            flex-shrink: 0;
            padding: 1px 4px;
            font-size: 0.65rem;
            border-radius: 2px;
            min-width: auto;
            line-height: 1;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .delete-file-btn:hover {
            transform: scale(1.1);
            transition: transform 0.2s ease;
        }
        .delete-file-btn i {
            font-size: 0.6rem;
        }
        .delete-user-btn {
            white-space: nowrap;
        }
        .hidden-by-filter {
            display: none !important;
        }
        @media (max-width: 768px) {
            .evidence-categories {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($feedback_message): ?>
            <div class="feedback-message alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo htmlspecialchars($feedback_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($debug_info)): ?>
            <div class="debug-info">
                <h4>Información de Depuración</h4>
                <pre><?php echo htmlspecialchars(implode("\n", $debug_info)); ?></pre>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['admin'])): ?>
            <!-- Formulario de Login de Admin -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-center">Login de Administrador</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" action="admin.php">
                                <input type="hidden" name="action" value="admin_login">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Usuario</label>
                                    <input type="text" class="form-control" id="username" name="username" placeholder="admin">
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="admin123">
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Vista de Administrador -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Panel de Administración - Concurso Nutrileche 2025</h1>
                <form method="post" action="admin.php" style="display: inline;">
                    <input type="hidden" name="action" value="admin_logout">
                    <button type="submit" class="btn btn-danger">Cerrar Sesión</button>
                </form>
            </div>

            <!-- Formulario para Añadir un Nuevo Departamento -->
            <div class="add-department-section">
                <h3>Añadir Nuevo Departamento</h3>
                <form method="post" action="admin.php">
                    <input type="hidden" name="action" value="add_department">
                    <div class="mb-3">
                        <label for="new_departamento" class="form-label">Nombre del Departamento</label>
                        <input type="text" class="form-control" id="new_departamento" name="new_departamento" placeholder="Ej. Nueva Región" required>
                    </div>
                    <button type="submit" class="btn btn-success">Añadir Departamento</button>
                </form>
            </div>

            <!-- Formulario de Permisos de Departamentos -->
            <div class="permissions-section">
                <h3>Control de Permisos por Departamento</h3>
                <form method="post" action="admin.php">
                    <input type="hidden" name="action" value="update_permissions">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Departamento</th>
                                <th>Crear Nueva Cuenta</th>
                                <th>Editar Envíos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($department_permissions as $index => $perm): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars(displayDepartamento($perm['departamento'])); ?>
                                        <input type="hidden" name="departamentos[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($perm['departamento']); ?>">
                                    </td>
                                    <td>
                                        <input type="hidden" name="can_create_account[<?php echo $index; ?>]" value="0">
                                        <input type="checkbox" name="can_create_account[<?php echo $index; ?>]" value="1" <?php echo $perm['can_create_account'] ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="hidden" name="can_edit_submission[<?php echo $index; ?>]" value="0">
                                        <input type="checkbox" name="can_edit_submission[<?php echo $index; ?>]" value="1" <?php echo $perm['can_edit_submission'] ? 'checked' : ''; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary">Guardar Permisos</button>
                </form>
            </div>

            <!-- Vista de Evidencias -->
            <div class="evidence-monitoring-section">
                <h3>Monitoreo de Evidencias</h3>
                
                <!-- Filtros -->
                <div class="filters-section mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="department-filter" class="form-label">Filtrar por Departamento</label>
                            <select class="form-select" id="department-filter">
                                <option value="">Todos los departamentos</option>
                                <?php
                                // Obtener lista única de departamentos
                                $unique_departments = [];
                                foreach ($evidences_by_user as $entry) {
                                    $dept = $entry['user']['departamento'];
                                    if (!in_array($dept, $unique_departments)) {
                                        $unique_departments[] = $dept;
                                    }
                                }
                                sort($unique_departments);
                                foreach ($unique_departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                        <?php echo htmlspecialchars(displayDepartamento($dept)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="sort-order" class="form-label">Ordenar por</label>
                            <select class="form-select" id="sort-order">
                                <option value="name">Nombre (A-Z)</option>
                                <option value="last_update">Última Actualización (Reciente)</option>
                                <option value="registration">Fecha de Registro (Reciente)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <button class="btn btn-secondary" id="clear-filters">Limpiar Filtros</button>
                            <span class="ms-2" id="results-count"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Descargas por Departamento</label>
                            <div>
                                <select class="form-select mb-2" id="download-department">
                                    <option value="">Seleccionar departamento</option>
                                    <?php foreach ($unique_departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>">
                                            <?php echo htmlspecialchars(displayDepartamento($dept)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-success btn-sm w-100" id="download-department-btn" disabled>
                                    <i class="fas fa-download"></i> Descargar Departamento
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas Rápidas -->
                <div class="stats-section mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h5>Total Usuarios</h5>
                                <p class="stat-number"><?php echo count($evidences_by_user); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h5>Con Proyectos</h5>
                                <p class="stat-number" id="stat-proyectos">0</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h5>Con Redacciones</h5>
                                <p class="stat-number" id="stat-redacciones">0</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h5>Con Fotos/Videos</h5>
                                <p class="stat-number" id="stat-multimedia">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="stat-card bg-light">
                                <h5><i class="fas fa-chart-line"></i> Actividad Reciente</h5>
                                <p class="mb-2" id="stat-recent-activity">Cargando...</p>
                                <small class="text-muted">Usuarios con actividad en los últimos 7 días</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Usuarios -->
            <?php if (empty($evidences_by_user)): ?>
                <div class="alert alert-info" role="alert">
                    No hay usuarios registrados todavía.
                </div>
            <?php else: ?>
                    <div id="users-container">
                <?php foreach ($evidences_by_user as $entry): ?>
                            <div class="submission-section user-entry" 
                                 data-department="<?php echo htmlspecialchars($entry['user']['departamento']); ?>"
                                 data-user-id="<?php echo htmlspecialchars($entry['user']['id']); ?>"
                                 data-user-name="<?php echo htmlspecialchars($entry['user']['nombre']); ?>"
                                 data-last-update="<?php echo htmlspecialchars($entry['last_update'] ?: ''); ?>"
                                 data-registration="<?php echo htmlspecialchars($entry['user']['created_at'] ?? ''); ?>">
                                <div class="user-header">
                                    <h3>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($entry['user']['nombre']); ?> 
                                        <small class="text-muted">(ID: <?php echo htmlspecialchars($entry['user']['id']); ?>)</small>
                                    </h3>
                                    <div class="user-actions">
                                        <button class="btn btn-outline-success btn-sm download-user-btn" 
                                                data-user-id="<?php echo htmlspecialchars($entry['user']['id']); ?>"
                                                data-user-name="<?php echo htmlspecialchars($entry['user']['nombre']); ?>">
                                            <i class="fas fa-download"></i> Descargar ZIP
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm delete-user-btn ms-1" 
                                                data-user-id="<?php echo htmlspecialchars($entry['user']['id']); ?>"
                                                data-user-name="<?php echo htmlspecialchars($entry['user']['nombre']); ?>">
                                            <i class="fas fa-trash-alt"></i> Eliminar Usuario
                                        </button>
                                        <span class="badge bg-primary department-badge ms-2">
                                            <?php echo htmlspecialchars(displayDepartamento($entry['user']['departamento'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="user-info">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong><i class="fas fa-school"></i> Colegio:</strong> <?php echo htmlspecialchars($entry['user']['institucion']); ?></p>
                                            <p><strong><i class="fas fa-map-marker-alt"></i> Ciudad:</strong> <?php echo htmlspecialchars($entry['user']['ciudad']); ?></p>
                                            <p><strong><i class="fas fa-users"></i> Sección:</strong> <?php echo htmlspecialchars($entry['user']['seccion'] ?: 'N/A'); ?></p>
                                            <p><strong><i class="fas fa-calendar-plus"></i> Registro:</strong> 
                                                <?php 
                                                if (isset($entry['user']['created_at']) && $entry['user']['created_at']) {
                                                    echo htmlspecialchars(date('d/m/Y H:i', strtotime($entry['user']['created_at'])));
                                                } else {
                                                    echo 'No disponible';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($entry['user']['email']); ?></p>
                                            <p><strong><i class="fas fa-phone"></i> Teléfono:</strong> <?php echo htmlspecialchars($entry['user']['telefono']); ?></p>
                                            <p><strong><i class="fas fa-clock"></i> Turno:</strong> <?php echo htmlspecialchars($entry['user']['turno'] ?: 'N/A'); ?></p>
                                            <p><strong><i class="fas fa-graduation-cap"></i> Modalidad:</strong> <?php echo htmlspecialchars($entry['user']['modalidad']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Información de fechas -->
                                    <div class="row mt-3 pt-3 border-top">
                                        <div class="col-md-12">
                                            <p class="mb-1"><strong><i class="fas fa-sync-alt text-primary"></i> Última Actualización:</strong></p>
                                            <p class="text-primary fw-bold">
                                                <?php 
                                                if ($entry['last_update']) {
                                                    echo htmlspecialchars(date('d/m/Y H:i', strtotime($entry['last_update'])));
                                                } else {
                                                    echo 'No disponible';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="evidences-section">
                                    <h4><i class="fas fa-folder-open"></i> Evidencias Cargadas:</h4>
                        <?php if (empty($entry['evidences'])): ?>
                                        <div class="alert alert-warning" role="alert">
                                            <i class="fas fa-exclamation-triangle"></i> No hay evidencias cargadas para este usuario.
                                        </div>
                                    <?php else: ?>
                                        <?php
                                        // Categorizar evidencias
                                        $categorized_evidences = [
                                            'proyecto' => [],
                                            'redacciones' => [],
                                            'fotos' => [],
                                            'videos' => []
                                        ];
                                        
                                        foreach ($entry['evidences'] as $evidence) {
                                            if (isset($categorized_evidences[$evidence['type']])) {
                                                $categorized_evidences[$evidence['type']][] = $evidence;
                                            }
                                        }
                                        ?>
                                        
                                        <div class="evidence-categories">
                                            <!-- Proyectos -->
                                            <div class="evidence-category" data-type="proyecto">
                                                <h5><i class="fas fa-file-alt text-primary"></i> Proyectos (<?php echo count($categorized_evidences['proyecto']); ?>)</h5>
                                                <?php if (empty($categorized_evidences['proyecto'])): ?>
                                                    <p class="text-muted">No hay proyectos cargados</p>
                        <?php else: ?>
                            <div class="file-list">
                                                        <?php foreach ($categorized_evidences['proyecto'] as $evidence): ?>
                                    <div class="file-item">
                                                                <div class="file-content">
                                                                    <i class="fas fa-file-alt text-primary me-2"></i>
                                                                    <a href="<?php echo htmlspecialchars($evidence['file_path']); ?>" target="_blank">
                                                                        <?php echo htmlspecialchars($evidence['file_name']); ?>
                                                                    </a>
                                                                </div>
                                                                <button class="btn btn-outline-danger btn-sm delete-file-btn" 
                                                                        data-file-path="<?php echo htmlspecialchars($evidence['file_path']); ?>"
                                                                        data-file-name="<?php echo htmlspecialchars($evidence['file_name']); ?>"
                                                                        data-user-id="<?php echo htmlspecialchars($entry['user']['id']); ?>">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                                            </div>

                                            <!-- Redacciones -->
                                            <div class="evidence-category" data-type="redacciones">
                                                <h5><i class="fas fa-file-alt text-success"></i> Redacciones (<?php echo count($categorized_evidences['redacciones']); ?>)</h5>
                                                <?php if (empty($categorized_evidences['redacciones'])): ?>
                                                    <p class="text-muted">No hay redacciones cargadas</p>
                                                <?php else: ?>
                                                    <div class="file-list">
                                                        <?php foreach ($categorized_evidences['redacciones'] as $evidence): ?>
                                                            <div class="file-item">
                                                                <div class="file-content">
                                                                    <i class="fas fa-file-alt text-success me-2"></i>
                                                                    <a href="<?php echo htmlspecialchars($evidence['file_path']); ?>" target="_blank">
                                                                        <?php echo htmlspecialchars($evidence['file_name']); ?>
                                                                    </a>
                                                                </div>
                                                                <button class="btn btn-outline-danger btn-sm delete-file-btn" 
                                                                        data-file-path="<?php echo htmlspecialchars($evidence['file_path']); ?>"
                                                                        data-file-name="<?php echo htmlspecialchars($evidence['file_name']); ?>"
                                                                        data-user-id="<?php echo htmlspecialchars($entry['user']['id']); ?>">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                    </div>
                <?php endforeach; ?>
                                                    </div>
            <?php endif; ?>
                                            </div>

                                            <!-- Fotos -->
                                            <div class="evidence-category" data-type="fotos">
                                                <h5><i class="fas fa-image text-warning"></i> Fotos (<?php echo count($categorized_evidences['fotos']); ?>)</h5>
                                                <?php if (empty($categorized_evidences['fotos'])): ?>
                                                    <p class="text-muted">No hay fotos cargadas</p>
                                                <?php else: ?>
                                                    <div class="file-list">
                                                        <?php foreach ($categorized_evidences['fotos'] as $evidence): ?>
                                                            <div class="file-item">
                                                                <div class="file-content">
                                                                    <i class="fas fa-image text-warning me-2"></i>
                                                                    <a href="<?php echo htmlspecialchars($evidence['file_path']); ?>" target="_blank">
                                                                        <?php echo htmlspecialchars($evidence['file_name']); ?>
                                                                    </a>
                                                                </div>
                                                                <button class="btn btn-outline-danger btn-sm delete-file-btn" 
                                                                        data-file-path="<?php echo htmlspecialchars($evidence['file_path']); ?>"
                                                                        data-file-name="<?php echo htmlspecialchars($evidence['file_name']); ?>"
                                                                        data-user-id="<?php echo htmlspecialchars($entry['user']['id']); ?>">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Videos -->
                                            <div class="evidence-category" data-type="videos">
                                                <h5><i class="fas fa-video text-danger"></i> Videos (<?php echo count($categorized_evidences['videos']); ?>)</h5>
                                                <?php if (empty($categorized_evidences['videos'])): ?>
                                                    <p class="text-muted">No hay videos cargados</p>
                                                <?php else: ?>
                                                    <div class="file-list">
                                                        <?php foreach ($categorized_evidences['videos'] as $evidence): ?>
                                                            <div class="file-item">
                                                                <div class="file-content">
                                                                    <i class="fas fa-video text-danger me-2"></i>
                                                                    <a href="<?php echo htmlspecialchars($evidence['file_path']); ?>" target="_blank">
                                                                        <?php echo htmlspecialchars($evidence['file_name']); ?>
                                                                    </a>
                                                                </div>
                                                                <button class="btn btn-outline-danger btn-sm delete-file-btn" 
                                                                        data-file-path="<?php echo htmlspecialchars($evidence['file_path']); ?>"
                                                                        data-file-name="<?php echo htmlspecialchars($evidence['file_name']); ?>"
                                                                        data-user-id="<?php echo htmlspecialchars($entry['user']['id']); ?>">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Función para mostrar modal
        function showModal(title, message) {
            // Crear modal si no existe
            if ($('#feedbackModal').length === 0) {
                const modalHTML = `
                    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalTitle">Mensaje</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" id="modalBody"></div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHTML);
            }
            
            $('#modalTitle').text(title);
            
            // Verificar si el mensaje es HTML o texto plano
            if (message.includes('<') && message.includes('>')) {
                $('#modalBody').html(message);
            } else {
                $('#modalBody').text(message);
            }
            
            $('#feedbackModal').modal('show');
        }

        $(document).ready(function() {
            // Calculate and display statistics
            function updateStatistics() {
                let proyectos = 0, redacciones = 0, multimedia = 0, recentActivity = 0;
                const oneWeekAgo = new Date();
                oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
                
                $('.user-entry:visible').each(function() {
                    if ($(this).find('.evidence-category[data-type="proyecto"] .file-item').length > 0) {
                        proyectos++;
                    }
                    if ($(this).find('.evidence-category[data-type="redacciones"] .file-item').length > 0) {
                        redacciones++;
                    }
                    if ($(this).find('.evidence-category[data-type="fotos"] .file-item').length > 0 || 
                        $(this).find('.evidence-category[data-type="videos"] .file-item').length > 0) {
                        multimedia++;
                    }
                    
                    // Check for recent activity
                    const lastUpdate = $(this).data('last-update');
                    if (lastUpdate && new Date(lastUpdate) > oneWeekAgo) {
                        recentActivity++;
                    }
                });
                
                $('#stat-proyectos').text(proyectos);
                $('#stat-redacciones').text(redacciones);
                $('#stat-multimedia').text(multimedia);
                $('#stat-recent-activity').text(`${recentActivity} usuarios activos`);
            }
            
            // Filter function
            function filterUsers() {
                const departmentFilter = $('#department-filter').val();
                let visibleCount = 0;
                
                $('.user-entry').each(function() {
                    let showUser = true;
                    
                    // Department filter
                    if (departmentFilter && $(this).data('department') !== departmentFilter) {
                        showUser = false;
                    }
                    
                    if (showUser) {
                        $(this).removeClass('hidden-by-filter').show();
                        visibleCount++;
                    } else {
                        $(this).addClass('hidden-by-filter').hide();
                    }
                });
                
                // Update results count
                $('#results-count').text(`Mostrando ${visibleCount} usuario(s)`);
                
                // Update statistics
                updateStatistics();
            }
            
            // Sort function
            function sortUsers() {
                const sortOrder = $('#sort-order').val();
                const $container = $('#users-container');
                const $users = $('.user-entry').get();
                
                $users.sort(function(a, b) {
                    const $a = $(a);
                    const $b = $(b);
                    
                    switch(sortOrder) {
                        case 'name':
                            const nameA = $a.data('user-name').toLowerCase();
                            const nameB = $b.data('user-name').toLowerCase();
                            return nameA.localeCompare(nameB);
                            
                        case 'last_update':
                            const updateA = $a.data('last-update');
                            const updateB = $b.data('last-update');
                            if (!updateA && !updateB) return 0;
                            if (!updateA) return 1;
                            if (!updateB) return -1;
                            return new Date(updateB) - new Date(updateA);
                            
                        case 'registration':
                            const regA = $a.data('registration');
                            const regB = $b.data('registration');
                            return new Date(regB) - new Date(regA);
                            
                        default:
                            return 0;
                    }
                });
                
                $.each($users, function(index, item) {
                    $container.append(item);
                });
            }
            
            // Event listeners for filters and sorting
            $('#department-filter').on('change', filterUsers);
            $('#sort-order').on('change', sortUsers);
            
            // Clear filters button
            $('#clear-filters').on('click', function() {
                $('#department-filter').val('');
                $('#sort-order').val('name');
                $('.user-entry').removeClass('hidden-by-filter').show();
                $('#results-count').text('');
                sortUsers();
                updateStatistics();
            });
            
            // Enable/disable department download button
            $('#download-department').on('change', function() {
                const selected = $(this).val();
                $('#download-department-btn').prop('disabled', !selected);
            });
            
            // Download department ZIP
            $('#download-department-btn').on('click', function() {
                const department = $('#download-department').val();
                if (!department) return;
                
                const $btn = $(this);
                const originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Preparando...').prop('disabled', true);
                
                // Create a form to submit the download request
                const form = $('<form>', {
                    method: 'POST',
                    action: 'admin.php'
                });
                form.append($('<input>', { type: 'hidden', name: 'action', value: 'download_department' }));
                form.append($('<input>', { type: 'hidden', name: 'department', value: department }));
                
                $('body').append(form);
                form.submit();
                form.remove();
                
                // Reset button after a delay
                setTimeout(function() {
                    $btn.html(originalText).prop('disabled', false);
                }, 3000);
            });
            
            // Download individual user ZIP
            $(document).on('click', '.download-user-btn', function() {
                const userId = $(this).data('user-id');
                const userName = $(this).data('user-name');
                
                const $btn = $(this);
                const originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Preparando...').prop('disabled', true);
                
                // Create a form to submit the download request
                const form = $('<form>', {
                    method: 'POST',
                    action: 'admin.php'
                });
                form.append($('<input>', { type: 'hidden', name: 'action', value: 'download_user' }));
                form.append($('<input>', { type: 'hidden', name: 'user_id', value: userId }));
                
                $('body').append(form);
                form.submit();
                form.remove();
                
                // Reset button after a delay
                setTimeout(function() {
                    $btn.html(originalText).prop('disabled', false);
                }, 3000);
            });

            // Delete individual file
            $(document).on('click', '.delete-file-btn', function() {
                const fileName = $(this).data('file-name');
                const filePath = $(this).data('file-path');
                const userId = $(this).data('user-id');
                
                if (!confirm(`¿Estás seguro de que quieres eliminar el archivo "${fileName}"?\n\nEsta acción no se puede deshacer.`)) {
                    return;
                }
                
                const $btn = $(this);
                const $fileItem = $btn.closest('.file-item');
                const originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
                
                $.ajax({
                    url: 'admin.php',
                    method: 'POST',
                    data: {
                        action: 'delete_file',
                        file_path: filePath,
                        user_id: userId
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            $fileItem.fadeOut(300, function() {
                                $(this).remove();
                                updateStatistics();
                                
                                // Update file count in category header
                                const $category = $fileItem.closest('.evidence-category');
                                const $header = $category.find('h5');
                                const currentCount = $category.find('.file-item').length;
                                const categoryName = $header.text().split('(')[0].trim();
                                $header.html(`<i class="${$header.find('i').attr('class')}"></i> ${categoryName} (${currentCount})`);
                            });
                            showModal('Éxito', data.message);
                        } else {
                            $btn.html(originalText).prop('disabled', false);
                            showModal('Error', data.message);
                        }
                    },
                    error: function() {
                        $btn.html(originalText).prop('disabled', false);
                        showModal('Error', 'Error al eliminar el archivo.');
                    }
                });
            });

            // Delete entire user
            $(document).on('click', '.delete-user-btn', function() {
                const userId = $(this).data('user-id');
                const userName = $(this).data('user-name');
                
                if (!confirm(`¿Estás seguro de que quieres eliminar completamente al usuario "${userName}"?\n\nEsto eliminará:\n- Todos sus datos personales\n- Todas sus evidencias\n- Todos sus archivos\n\nEsta acción NO se puede deshacer.`)) {
                    return;
                }
                
                // Double confirmation for user deletion
                if (!confirm('¿ESTÁS COMPLETAMENTE SEGURO?\n\nEsta es tu última oportunidad para cancelar la eliminación del usuario.')) {
                    return;
                }
                
                const $btn = $(this);
                const $userEntry = $btn.closest('.user-entry');
                const originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Eliminando...').prop('disabled', true);
                
                $.ajax({
                    url: 'admin.php',
                    method: 'POST',
                    data: {
                        action: 'delete_user',
                        user_id: userId
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            $userEntry.fadeOut(300, function() {
                                $(this).remove();
                                updateStatistics();
                                
                                // Update results count
                                const totalUsers = $('.user-entry').length;
                                $('#results-count').text(`Mostrando ${totalUsers} usuario(s)`);
                            });
                            showModal('Éxito', data.message);
                        } else {
                            $btn.html(originalText).prop('disabled', false);
                            showModal('Error', data.message);
                        }
                    },
                    error: function() {
                        $btn.html(originalText).prop('disabled', false);
                        showModal('Error', 'Error al eliminar el usuario.');
                    }
                });
            });
            
            // Initialize statistics on page load
            updateStatistics();
            
            // Set initial results count
            const totalUsers = $('.user-entry').length;
            $('#results-count').text(`Mostrando ${totalUsers} usuario(s)`);
        });
    </script>
</body>
</html>