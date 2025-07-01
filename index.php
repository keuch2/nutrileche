<?php
session_start();

// Incluir PHPMailer manualmente
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'c2780418_nutri';
$username = 'c2780418_nutri';
$password = '41keDUlena';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Directorio base para almacenar archivos subidos
$base_upload_dir = 'uploads/';

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

// Generar OTP
function generateOTP() {
    return rand(100000, 999999);
}

// Enviar OTP usando PHPMailer
function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };

        $mail->isSMTP();
        $mail->Host = 'c2780418.ferozo.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@capainlac.com.py';
        $mail->Password = 'qb38TbRBm5';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no-reply@capainlac.com.py', 'Concurso Nutrileche');
        $mail->addAddress($email);
        $mail->Subject = 'Tu codigo para ingresar al Concurso Nutrileche 2025';
        $mail->Body = "Hola,\n\ncódigo para acceder al Concurso Nutrileche es: $otp\n\nEste código es válido por 10 minutos.\n\nSaludos,\nEquipo Nutrileche";
        $mail->AltBody = "Hola,\n\ncódigo para acceder al Concurso Nutrileche es: $otp\n\nEste código es válido por 10 minutos.\n\nSaludos,\nEquipo Nutrileche";

        $mail->send();
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_time'] = time();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar el email: " . $mail->ErrorInfo);
        return false;
    }
}

// Validar OTP
function validateOTP($email, $otp) {
    if (!isset($_SESSION['otp']) || $_SESSION['otp'] != $otp || $_SESSION['otp_email'] != $email) {
        return false;
    }

    $current_time = time();
    $otp_time = $_SESSION['otp_time'] ?? 0;
    if (($current_time - $otp_time) > 600) {
        unset($_SESSION['otp']);
        unset($_SESSION['otp_email']);
        unset($_SESSION['otp_time']);
        return false;
    }

    return true;
}

// Verificar si el departamento tiene permiso para crear una cuenta
function checkDepartmentPermissions($pdo, $departamento, $permission_type) {
    $departamento = normalizeDepartamento($departamento);
    $stmt = $pdo->prepare("SELECT $permission_type FROM department_permissions WHERE departamento = ?");
    $stmt->execute([$departamento]);
    $result = $stmt->fetchColumn();
    return $result === 1; // Retorna true si el permiso está habilitado
}

// Obtener departamentos disponibles con sus permisos
function getAvailableDepartments($pdo) {
    $stmt = $pdo->query("SELECT departamento, can_create_account, can_edit_submission FROM department_permissions");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Manejar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF)']));
    }

    $data = [
        'departamento' => normalizeDepartamento(htmlspecialchars(trim($_POST['departamento'] ?? ''), ENT_QUOTES, 'UTF-8')),
        'nombre' => htmlspecialchars(trim($_POST['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'telefono' => htmlspecialchars(trim($_POST['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'ciudad' => htmlspecialchars(trim($_POST['ciudad'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'institucion' => htmlspecialchars(trim($_POST['institucion'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'seccion' => htmlspecialchars(trim($_POST['seccion'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'turno' => htmlspecialchars(trim($_POST['turno'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'modalidad' => htmlspecialchars(trim($_POST['modalidad'] ?? ''), ENT_QUOTES, 'UTF-8'),
    ];

    // Validar campos obligatorios
    if (empty($data['departamento']) || empty($data['nombre']) || empty($data['email']) || empty($data['telefono']) || empty($data['ciudad']) || empty($data['institucion']) || empty($data['seccion']) || empty($data['turno']) || empty($data['modalidad'])) {
        die(json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos obligatorios.']));
    }

    // Validar que el departamento exista en la base de datos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM department_permissions WHERE departamento = ?");
    $stmt->execute([$data['departamento']]);
    if ($stmt->fetchColumn() == 0) {
        die(json_encode(['success' => false, 'message' => 'El departamento seleccionado no es válido.']));
    }

    // Verificar si el departamento tiene permiso para crear una cuenta
    if (!checkDepartmentPermissions($pdo, $data['departamento'], 'can_create_account')) {
        die(json_encode(['success' => false, 'message' => 'El registro está cerrado para tu departamento.']));
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Email inválido']));
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetchColumn() > 0) {
        die(json_encode(['success' => false, 'message' => 'El email ya está registrado']));
    }

    $stmt = $pdo->prepare("INSERT INTO users (departamento, nombre, email, telefono, ciudad, institucion, seccion, turno, modalidad) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['departamento'],
        $data['nombre'],
        $data['email'],
        $data['telefono'],
        $data['ciudad'],
        $data['institucion'],
        $data['seccion'],
        $data['turno'],
        $data['modalidad']
    ]);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user'] = $user;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        die(json_encode(['success' => true, 'message' => 'Registro exitoso. Bienvenido(a) a la carga de evidencias.']));
    } else {
        die(json_encode(['success' => false, 'message' => 'Error al registrar el usuario. Por favor, intenta de nuevo.']));
    }
}

// Manejar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $otp = htmlspecialchars(trim($_POST['otp'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Email inválido']));
    }

    if (empty($otp)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() == 0) {
            die(json_encode(['success' => false, 'message' => 'El email no está registrado']));
        }

        $otp = generateOTP();
        if (sendOTP($email, $otp)) {
            die(json_encode(['success' => true, 'message' => 'Se ha enviado un código a tu email.']));
        } else {
            die(json_encode(['success' => false, 'message' => 'Error al enviar el código. Por favor, intenta de nuevo. Verifica tu conexión o contacta a soporte@capainlac.com.py']));
        }
    }

    if (validateOTP($email, $otp)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user'] = $user;
            unset($_SESSION['otp']);
            unset($_SESSION['otp_email']);
            unset($_SESSION['otp_time']);
            die(json_encode(['success' => true, 'message' => 'Login exitoso']));
        } else {
            die(json_encode(['success' => false, 'message' => 'Usuario no encontrado']));
        }
    } else {
        die(json_encode(['success' => false, 'message' => 'Código inválido o expirado']));
    }
}

// Manejar actualización de datos del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    if (!isset($_SESSION['user'])) {
        die(json_encode(['success' => false, 'message' => 'Debes iniciar sesión']));
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF)']));
    }

    // Verificar si el departamento del usuario tiene permiso para editar
    if (!checkDepartmentPermissions($pdo, $_SESSION['user']['departamento'], 'can_edit_submission')) {
        die(json_encode(['success' => false, 'message' => 'La edición está cerrada para tu departamento.']));
    }

    $data = [
        'nombre' => htmlspecialchars(trim($_POST['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'telefono' => htmlspecialchars(trim($_POST['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'),
    ];

    // Validar campos obligatorios
    if (empty($data['nombre']) || empty($data['email']) || empty($data['telefono'])) {
        die(json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos obligatorios.']));
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Email inválido']));
    }

    // Verificar si el email ya está registrado por otro usuario
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$data['email'], $_SESSION['user']['id']]);
    if ($stmt->fetchColumn() > 0) {
        die(json_encode(['success' => false, 'message' => 'El email ya está registrado por otro usuario']));
    }

    // Actualizar los datos del usuario
    $stmt = $pdo->prepare("UPDATE users SET nombre = ?, email = ?, telefono = ? WHERE id = ?");
    $stmt->execute([
        $data['nombre'],
        $data['email'],
        $data['telefono'],
        $_SESSION['user']['id']
    ]);

    // Actualizar la sesión con los nuevos datos
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user'] = $user;

    die(json_encode(['success' => true, 'message' => 'Datos actualizados con éxito']));
}

// Manejar logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_unset();
    session_destroy();
    die(json_encode(['success' => true, 'message' => 'Sesión cerrada con éxito']));
}

// Manejar carga de archivos (directamente a la base de datos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!isset($_SESSION['user'])) {
        die(json_encode(['success' => false, 'message' => 'Debes iniciar sesión']));
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF)']));
    }

    // Verificar si el departamento del usuario tiene permiso para editar
    if (!checkDepartmentPermissions($pdo, $_SESSION['user']['departamento'], 'can_edit_submission')) {
        die(json_encode(['success' => false, 'message' => 'La edición está cerrada para tu departamento.']));
    }

    $type = $_POST['type'] ?? '';
    $allowed_types = [
        'proyecto' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'redacciones' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'],
        'fotos' => ['image/jpeg', 'image/png'],
        'videos' => ['video/mp4'],
    ];

    if (!isset($allowed_types[$type])) {
        die(json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']));
    }

    // Crear la jerarquía de carpetas: Departamento/Ciudad/Institucion/Type
    $departamento = preg_replace('/[^A-Za-z0-9\-]/', '_', $_SESSION['user']['departamento']);
    $ciudad = preg_replace('/[^A-Za-z0-9\-]/', '_', $_SESSION['user']['ciudad']);
    $institucion = preg_replace('/[^A-Za-z0-9\-]/', '_', $_SESSION['user']['institucion']);
    $type_dir = ucfirst($type);

    $upload_path = $base_upload_dir . $departamento . '/' . $ciudad . '/' . $institucion . '/' . $type_dir . '/';

    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }

    $files = $_FILES['files'];
    $uploaded_files = [];
    $max_size = 200 * 1024 * 1024; // 200MB

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['size'][$i] > $max_size) {
            die(json_encode(['success' => false, 'message' => 'Archivo demasiado grande (máximo 200MB)']));
        }

        if (!in_array($files['type'][$i], $allowed_types[$type])) {
            die(json_encode(['success' => false, 'message' => 'Formato de archivo no permitido']));
        }

        $file_name = uniqid() . '_' . basename($files['name'][$i]);
        $file_path = $upload_path . $file_name;

        if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
            $stmt = $pdo->prepare("INSERT INTO evidences (user_id, type, file_name, file_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user']['id'], $type, $files['name'][$i], $file_path]);
            $uploaded_files[] = [
                'original_name' => $files['name'][$i],
                'file_path' => $file_path,
                'file_type' => $files['type'][$i]
            ];
        } else {
            die(json_encode(['success' => false, 'message' => 'Error al mover el archivo']));
        }
    }

    // Preparar información para el email
    $file_names = array_map(function($file) {
        return $file['original_name'];
    }, $uploaded_files);
    
    $file_list_html = '<ul>';
    foreach ($file_names as $name) {
        $file_list_html .= '<li>' . htmlspecialchars($name) . '</li>';
    }
    $file_list_html .= '</ul>';
    
    // Enviar email de confirmación
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'c2780418.ferozo.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@capainlac.com.py';
        $mail->Password = 'qb38TbRBm5';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no-reply@capainlac.com.py', 'Concurso Nutrileche');
        $mail->addAddress($_SESSION['user']['email']);
        
        $mail->isHTML(true);
        $mail->Subject = 'Confirmación de carga de archivos - Concurso Nutrileche 2025';
        
        // Cuerpo del email en formato HTML
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    h2 { color: #4a4a4a; }
                    .footer { margin-top: 30px; font-size: 12px; color: #777; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>¡Archivos cargados exitosamente!</h2>
                    <p>Hola " . htmlspecialchars($_SESSION['user']['nombre']) . ",</p>
                    <p>Te confirmamos que los siguientes archivos han sido cargados correctamente para el Concurso Nutrileche 2025:</p>
                    " . $file_list_html . "
                    <p>Tipo de archivo: " . ucfirst($type) . "</p>
                    <p>Fecha y hora: " . date('d/m/Y H:i:s') . "</p>
                    <p>Si necesitas realizar algún cambio, puedes hacerlo iniciando sesión en nuestra plataforma.</p>
                    <p>¡Gracias por participar!</p>
                    <div class='footer'>
                        <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Versión alternativa en texto plano
        $mail->AltBody = "¡Archivos cargados exitosamente!\n\n" .
                        "Hola " . $_SESSION['user']['nombre'] . ",\n\n" .
                        "Te confirmamos que los siguientes archivos han sido cargados correctamente para el Concurso Nutrileche 2025:\n\n" .
                        implode("\n", $file_names) . "\n\n" .
                        "Tipo de archivo: " . ucfirst($type) . "\n" .
                        "Fecha y hora: " . date('d/m/Y H:i:s') . "\n\n" .
                        "Si necesitas realizar algún cambio, puedes hacerlo iniciando sesión en nuestra plataforma.\n\n" .
                        "¡Gracias por participar!\n\n" .
                        "Este es un mensaje automático, por favor no respondas a este correo.";

        $mail->send();
        // No necesitamos manejar el resultado del envío aquí, solo registrarlo
        error_log("Email de confirmación enviado a: " . $_SESSION['user']['email']);
    } catch (Exception $e) {
        // Solo registrar el error, no interrumpir el flujo
        error_log("Error al enviar email de confirmación: " . $mail->ErrorInfo);
    }

    die(json_encode([
        'success' => true, 
        'message' => 'Archivos subidos con éxito', 
        'files' => $uploaded_files,
        'emailSent' => true
    ]));
}

// Manejar eliminación de archivos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    if (!isset($_SESSION['user'])) {
        die(json_encode(['success' => false, 'message' => 'Debes iniciar sesión']));
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF)']));
    }

    // Verificar si el departamento del usuario tiene permiso para editar
    if (!checkDepartmentPermissions($pdo, $_SESSION['user']['departamento'], 'can_edit_submission')) {
        die(json_encode(['success' => false, 'message' => 'La edición está cerrada para tu departamento.']));
    }

    $file_path = $_POST['file_path'] ?? '';
    $file_name = $_POST['file_name'] ?? '';
    $type = $_POST['type'] ?? '';

    if (empty($file_path) || empty($file_name) || empty($type)) {
        die(json_encode(['success' => false, 'message' => 'Datos incompletos']));
    }

    // Verificar si el archivo existe en la base de datos
    $stmt = $pdo->prepare("SELECT id FROM evidences WHERE user_id = ? AND type = ? AND file_name = ? AND file_path = ?");
    $stmt->execute([$_SESSION['user']['id'], $type, $file_name, $file_path]);
    $evidence = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($evidence) {
        // Eliminar el archivo del sistema de archivos
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Eliminar el registro de la base de datos
        $stmt = $pdo->prepare("DELETE FROM evidences WHERE id = ?");
        $stmt->execute([$evidence['id']]);

        die(json_encode(['success' => true, 'message' => 'Archivo eliminado con éxito']));
    } else {
        die(json_encode(['success' => false, 'message' => 'Archivo no encontrado']));
    }
}

// Obtener evidencias del usuario actual
$evidences = [];
$can_edit = false;
if (isset($_SESSION['user'])) {
    $can_edit = checkDepartmentPermissions($pdo, $_SESSION['user']['departamento'], 'can_edit_submission');
    $stmt = $pdo->prepare("SELECT type, file_name, file_path FROM evidences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $evidences_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($evidences_raw as $evidence) {
        $evidences[$evidence['type']][] = [
            'file_name' => $evidence['file_name'],
            'file_path' => $evidence['file_path']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concurso Nutrileche 2025</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="header">
        <img src="img/logos.jpg">
    </div>

    <!-- Spinner de carga -->
    <div class="loading-spinner" id="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <?php if (!isset($_SESSION['user'])): ?>
        <!-- Pantalla de Inicio -->
        <div class="inicioimagen fade-transition" id="home-section">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <div id="formularioinicio">
                            <img src="img/tituloconcurso.png" id="tituloconcurso">
                            <h2>Ingreso de Concursantes</h2>
                            <div class="mb-3">
                                <label for="email" class="form-label">Escribe tu Correo Electrónico registrado</label>
                                <input type="email" class="form-control" id="email" placeholder="correo@ejemplo.com" autocomplete="email">
                            </div>
                            <div class="mb-3" id="otp-section" style="display: none;">
                                <label for="otp" class="form-label">Ingresa el código recibido</label>
                                <input type="text" class="form-control" id="otp" placeholder="123456" autocomplete="one-time-code">
                            </div>
                            <button class="btn btn-primary w-100" id="login-btn">Ingresar a la Plataforma</button>
                            <button class="btn btn-secondary w-100 mt-2" id="register-btn">Registrarme por Primera Vez</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div id="dibujo">
                            <img src="img/dibujo.png">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                    <div class="text-center mt-4">
                    <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#instructivoModal">
                        <i class="fas fa-play-circle"></i> Ver Instructivo
                    </button>
                    <a href="assets/afiche.pdf" target="_blank" class="btn btn-success">
                        <i class="fas fa-download"></i> Descargar Afiche
                    </a>
                    <br/><br/>
                    </div>
                </div>
            </div>
        </div>

            

<!-- Modal para el video instructivo -->
<div class="modal fade" id="instructivoModal" tabindex="-1" aria-labelledby="instructivoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="instructivoModalLabel">Instructivo del Concurso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ratio ratio-16x9">
                    <video controls>
                        <source src="assets/nutrileche.mp4" type="video/mp4">
                        Tu navegador no soporta videos HTML5.
                    </video>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
                </div>

            </div>   
    
        </div>

        <!-- Formulario de Registro -->
        <div id="headerinterno"><img src="img/headerinterno.jpg" style="display: none;"></div>
        <div class="section fade-transition" id="register-section" style="display: none;">
            <h2>Registro</h2>
            <div class="mb-3">
                <label for="departamento" class="form-label">Departamento</label>
                <select class="form-select" id="departamento">
                    <option value="">Selecciona un departamento</option>
                    <?php 
                    // Obtener departamentos de la base de datos
                    $departamentos = getAvailableDepartments($pdo);
                    foreach ($departamentos as $dept) {
                        $disabled = $dept['can_create_account'] == 0 ? 'disabled' : '';
                        $dept_name = displayDepartamento($dept['departamento']);
                        echo "<option value=\"" . htmlspecialchars($dept['departamento']) . "\" $disabled>" . htmlspecialchars($dept_name) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div id="register-form-rest" style="display: none;">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre y Apellido</label>
                    <input type="text" class="form-control" id="nombre" autocomplete="name">
                </div>
                <div class="mb-3">
                    <label for="reg-email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="reg-email" autocomplete="email">
                </div>
                <div class="mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" autocomplete="tel">
                </div>
                <div class="mb-3">
                    <label for="ciudad" class="form-label">Ciudad</label>
                    <input type="text" class="form-control" id="ciudad" autocomplete="address-level2">
                </div>
                <div class="mb-3">
                    <label for="institucion" class="form-label">Institución Educativa</label>
                    <input type="text" class="form-control" id="institucion" autocomplete="organization">
                </div>
                <div class="mb-3">
                    <label for="seccion" class="form-label">Sección</label>
                    <select class="form-select" id="seccion">
                        <option value="">Seleccionar</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="turno" class="form-label">Turno</label>
                    <select class="form-select" id="turno">
                        <option value="">Seleccionar</option>
                        <option value="Mañana">Mañana</option>
                        <option value="Tarde">Tarde</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="modalidad" class="form-label">Modalidad</label>
                    <select class="form-select" id="modalidad">
                        <option value="">Seleccionar</option>
                        <option value="Regular">Regular</option>
                        <option value="JEE">JEE</option>
                        <option value="Plurigrado">Plurigrado</option>
                    </select>
                </div>
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button class="btn btn-secondary w-100" id="back-btn">Volver</button>
                <button class="btn btn-primary w-100 mt-2" id="submit-register-btn">Avanzar</button>
            </div>
        </div>
    <?php else: ?>
        <!-- Pantalla de Carga de Evidencias -->
        <div id="headerinterno"><img src="img/headerinterno.jpg"></div>
        <div class="container">
        <div class="fade-transition" id="evidence-section">
            <h2>Carga de Evidencias</h2>
            <div id="datousuario">
                <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['user']['nombre']); ?></p>
                <p><strong>Colegio:</strong> <?php echo htmlspecialchars($_SESSION['user']['institucion']); ?></p>
                <p><strong>Sección:</strong> <?php echo htmlspecialchars($_SESSION['user']['seccion'] ?: 'N/A'); ?></p>
                <p><strong>Turno:</strong> <?php echo htmlspecialchars($_SESSION['user']['turno'] ?: 'N/A'); ?></p>
                <p><strong>Ciudad:</strong> <?php echo htmlspecialchars($_SESSION['user']['ciudad']); ?></p>
                <p><strong>Departamento:</strong> <?php echo htmlspecialchars(displayDepartamento($_SESSION['user']['departamento'])); ?></p>
                <div class="user-links">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#editUserModal">Editar mis datos</a>
                    <a href="#" id="logout-link">Salir de la plataforma</a>
                </div>
            </div>

            <!-- Cargar Proyecto -->
            <div class="evidence-section">
                <h3>CARGAR PROYECTO</h3>
                <?php if ($can_edit): ?>
                    <small>Tamaño máximo: 200MB</small><br>
                    <input type="file" class="" id="proyecto-files" accept=".pdf,.doc,.docx" onchange="uploadFiles('proyecto')">
                    <div class="progress" id="proyecto-progress" style="display: none;">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                <?php else: ?>
                    <p class="text-danger">La edición está cerrada para tu departamento.</p>
                <?php endif; ?>
                <div class="file-list" id="proyecto-list">
                    <?php if (isset($evidences['proyecto'])): ?>
                        <?php foreach ($evidences['proyecto'] as $file): ?>
                            <div class="file-item" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-name="<?php echo htmlspecialchars($file['file_name']); ?>">
                                <i class="fas fa-file-alt"></i>
                                <span class="file-name"><a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($file['file_name']); ?></a></span>
                                <?php if ($can_edit): ?>
                                    <i class="fas fa-trash-alt delete-btn" onclick="deleteFile('proyecto', '<?php echo htmlspecialchars($file['file_path']); ?>', '<?php echo htmlspecialchars($file['file_name']); ?>')"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cargar Redacciones -->
            <div class="evidence-section">
                <h3>CARGAR REDACCIONES</h3>
                <?php if ($can_edit): ?>
                    <small>Cantidad Máxima: 10 - Tamaño máximo: 200MB</small><br>
                    <input type="file" class="" id="redacciones-files" accept=".pdf,.doc,.docx,.txt" multiple onchange="uploadFiles('redacciones')">
                    <div class="progress" id="redacciones-progress" style="display: none;">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                <?php else: ?>
                    <p class="text-danger">La edición está cerrada para tu departamento.</p>
                <?php endif; ?>
                <div class="file-list" id="redacciones-list">
                    <?php if (isset($evidences['redacciones'])): ?>
                        <?php foreach ($evidences['redacciones'] as $file): ?>
                            <div class="file-item" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-name="<?php echo htmlspecialchars($file['file_name']); ?>">
                                <i class="fas fa-file-alt"></i>
                                <span class="file-name"><a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($file['file_name']); ?></a></span>
                                <?php if ($can_edit): ?>
                                    <i class="fas fa-trash-alt delete-btn" onclick="deleteFile('redacciones', '<?php echo htmlspecialchars($file['file_path']); ?>', '<?php echo htmlspecialchars($file['file_name']); ?>')"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cargar Fotos -->
            <div class="evidence-section">
                <h3>CARGAR FOTOS</h3>
                <?php if ($can_edit): ?>
                    <small>Cantidad Máxima: 10 - Tamaño máximo: 200MB</small><br>
                    <input type="file" class="" id="fotos-files" accept=".jpg,.jpeg,.png" multiple onchange="uploadFiles('fotos')">
                    <div class="progress" id="fotos-progress" style="display: none;">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                <?php else: ?>
                    <p class="text-danger">La edición está cerrada para tu departamento.</p>
                <?php endif; ?>
                <div class="file-list" id="fotos-list">
                    <?php if (isset($evidences['fotos'])): ?>
                        <?php foreach ($evidences['fotos'] as $file): ?>
                            <div class="file-item" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-name="<?php echo htmlspecialchars($file['file_name']); ?>">
                                <i class="fas fa-image"></i>
                                <span class="file-name"><a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($file['file_name']); ?></a></span>
                                <?php if ($can_edit): ?>
                                    <i class="fas fa-trash-alt delete-btn" onclick="deleteFile('fotos', '<?php echo htmlspecialchars($file['file_path']); ?>', '<?php echo htmlspecialchars($file['file_name']); ?>')"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cargar Videos -->
            <div class="evidence-section">
                <h3>CARGAR VIDEOS</h3>
                <?php if ($can_edit): ?>
                    <small>Cantidad Máxima: 5 - Tamaño máximo: 200MB</small><br>
                    <input type="file" class="" id="videos-files" accept=".mp4" multiple onchange="uploadFiles('videos')">
                    <div class="progress" id="videos-progress" style="display: none;">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                <?php else: ?>
                    <p class="text-danger">La edición está cerrada para tu departamento.</p>
                <?php endif; ?>
                <div class="file-list" id="videos-list">
                    <?php if (isset($evidences['videos'])): ?>
                        <?php foreach ($evidences['videos'] as $file): ?>
                            <div class="file-item" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-name="<?php echo htmlspecialchars($file['file_name']); ?>">
                                <i class="fas fa-video"></i>
                                <span class="file-name"><a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($file['file_name']); ?></a></span>
                                <?php if ($can_edit): ?>
                                    <i class="fas fa-trash-alt delete-btn" onclick="deleteFile('videos', '<?php echo htmlspecialchars($file['file_path']); ?>', '<?php echo htmlspecialchars($file['file_name']); ?>')"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </div>
    <?php endif; ?>

    <!-- Patrocinadores -->
    <div class="sponsors">
        <img src="img/logos3.jpg">
    </div>

    <!-- Modal para Feedback -->
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

    <!-- Modal para Editar Datos -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Mis Datos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-nombre" class="form-label">Nombre y Apellido</label>
                        <input type="text" class="form-control" id="edit-nombre" value="<?php echo htmlspecialchars($_SESSION['user']['nombre']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="edit-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit-email" value="<?php echo htmlspecialchars($_SESSION['user']['email']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="edit-telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="edit-telefono" value="<?php echo htmlspecialchars($_SESSION['user']['telefono']); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="save-user-btn">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para subir archivos directamente
        function uploadFiles(type) {
            const files = $(`#${type}-files`)[0].files;
            if (files.length === 0) {
                showModal('Error', 'Por favor, selecciona al menos un archivo.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'upload');
            formData.append('type', type);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }

            const progressBar = $(`#${type}-progress`).show().find('.progress-bar');
            progressBar.css('width', '0%').text('0%');

            $('#loading-spinner').show();
            $.ajax({
                url: '',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressBar.css('width', percent + '%').text(percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    $('#loading-spinner').hide();
                    const data = JSON.parse(response);
                    
                    if (data.success) {
                        // Mostrar un modal de confirmación mejorado
                        let fileListHtml = '<ul class="list-group mb-3">';
                        data.files.forEach(file => {
                            fileListHtml += `
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="${getFileIcon(file.file_type)} me-2"></i>
                                        ${file.original_name}
                                    </div>
                                    <span class="badge bg-success rounded-pill">Cargado</span>
                                </li>
                            `;
                        });
                        fileListHtml += '</ul>';
                        
                        const emailInfo = data.emailSent ? 
                            `<div class="alert alert-info mt-3">
                                <i class="fas fa-envelope me-2"></i> Se ha enviado un correo de confirmación a ${$('#email').val() || '<?php echo isset($_SESSION['user']['email']) ? addslashes($_SESSION['user']['email']) : ''; ?>'}
                            </div>` : '';
                        
                        const modalContent = `
                            <div class="text-center mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                                <h4 class="mt-3">¡Archivos subidos con éxito!</h4>
                            </div>
                            <p>Los siguientes archivos han sido cargados correctamente:</p>
                            ${fileListHtml}
                            <p>Tipo: <strong>${type.charAt(0).toUpperCase() + type.slice(1)}</strong></p>
                            ${emailInfo}
                        `;
                        
                        showModal('Carga Exitosa', modalContent);
                        
                        // Actualizar la lista de archivos en la interfaz
                        const fileList = $(`#${type}-list`);
                        data.files.forEach(file => {
                            const fileItem = `
                                <div class="file-item" data-path="${file.file_path}" data-name="${file.original_name}">
                                    <i class="${getFileIcon(file.file_type)}"></i>
                                    <span class="file-name"><a href="${file.file_path}" target="_blank">${file.original_name}</a></span>
                                    <i class="fas fa-trash-alt delete-btn" onclick="deleteFile('${type}', '${file.file_path}', '${file.original_name}')"></i>
                                </div>
                            `;
                            fileList.append(fileItem);
                        });
                        $(`#${type}-files`).val(''); // Limpiar el input
                    } else {
                        // Mostrar modal de error
                        showModal('Error', data.message);
                    }
                    $(`#${type}-progress`).hide();
                },
                error: function(xhr, status, error) {
                    $('#loading-spinner').hide();
                    console.error('AJAX Error:', status, error);
                    showModal('Error', 'Error al subir los archivos.');
                    $(`#${type}-progress`).hide();
                }
            });
        }

        // Función para eliminar archivos
        function deleteFile(type, filePath, fileName) {
            const formData = new FormData();
            formData.append('action', 'delete_file');
            formData.append('type', type);
            formData.append('file_path', filePath);
            formData.append('file_name', fileName);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            $('#loading-spinner').show();
            $.ajax({
                url: '',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#loading-spinner').hide();
                    const data = JSON.parse(response);
                    showModal(data.success ? 'Éxito' : 'Error', data.message);
                    if (data.success) {
                        $(`#${type}-list .file-item[data-path="${filePath}"]`).remove();
                    }
                },
                error: function() {
                    $('#loading-spinner').hide();
                    showModal('Error', 'Error al eliminar el archivo.');
                }
            });
        }

        // Función para obtener el ícono según el tipo de archivo
        function getFileIcon(fileType) {
            if (fileType.includes('pdf') || fileType.includes('word') || fileType.includes('text')) {
                return 'fas fa-file-alt';
            } else if (fileType.includes('image')) {
                return 'fas fa-image';
            } else if (fileType.includes('video')) {
                return 'fas fa-video';
            }
            return 'fas fa-file';
        }

        // Función para obtener el tipo de archivo a partir del nombre
        function getFileType(fileName) {
            const extension = fileName.split('.').pop().toLowerCase();
            if (['pdf', 'doc', 'docx', 'txt'].includes(extension)) {
                return 'application/pdf';
            } else if (['jpg', 'jpeg', 'png'].includes(extension)) {
                return 'image/jpeg';
            } else if (['mp4'].includes(extension)) {
                return 'video/mp4';
            }
            return 'application/octet-stream';
        }

        // Función para mostrar modal
        function showModal(title, message) {
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
            // Mostrar formulario de registro
            $('#register-btn').click(function() {
                $('#home-section').hide();
                $('#register-section').show();
                $('#headerinterno').show();
            });

            // Volver a la pantalla de inicio
            $('#back-btn').click(function() {
                $('#register-section').addClass('fade-out');
                setTimeout(function() {
                    $('#register-section').hide();
                    $('#home-section').show().addClass('fade-in');
                    $('#register-form-rest').hide();
                    $('#departamento').val('');
                }, 500);
            });

            // Mostrar el resto del formulario cuando se selecciona un departamento
            $('#departamento').change(function() {
                if ($(this).val() !== '') {
                    $('#register-form-rest').show().addClass('fade-in');
                } else {
                    $('#register-form-rest').hide();
                }
            });

            // Manejar login
            $('#login-btn').click(function() {
                const email = $('#email').val();
                if (!email) {
                    showModal('Error', 'Por favor, ingresa un email válido.');
                    return;
                }

                if ($('#otp-section').is(':visible')) {
                    const otp = $('#otp').val();
                    $('#loading-spinner').show();
                    $.ajax({
                        url: '',
                        method: 'POST',
                        data: { action: 'login', email: email, otp: otp },
                        success: function(response) {
                            $('#loading-spinner').hide();
                            const data = JSON.parse(response);
                            showModal(data.success ? 'Éxito' : 'Error', data.message);
                            if (data.success) {
                                location.reload();
                            }
                        },
                        error: function() {
                            $('#loading-spinner').hide();
                            showModal('Error', 'Error al procesar la solicitud.');
                        }
                    });
                } else {
                    $('#loading-spinner').show();
                    $.ajax({
                        url: '',
                        method: 'POST',
                        data: { action: 'login', email: email },
                        success: function(response) {
                            $('#loading-spinner').hide();
                            const data = JSON.parse(response);
                            showModal(data.success ? 'Éxito' : 'Error', data.message);
                            if (data.success) {
                                $('#otp-section').show();
                            }
                        },
                        error: function() {
                            $('#loading-spinner').hide();
                            showModal('Error', 'Error al procesar la solicitud.');
                        }
                    });
                }
            });

            // Manejar registro
            $('#submit-register-btn').click(function() {
                const data = {
                    action: 'register',
                    departamento: $('#departamento').val(),
                    nombre: $('#nombre').val(),
                    email: $('#reg-email').val(),
                    telefono: $('#telefono').val(),
                    ciudad: $('#ciudad').val(),
                    institucion: $('#institucion').val(),
                    seccion: $('#seccion').val(),
                    turno: $('#turno').val(),
                    modalidad: $('#modalidad').val(),
                    csrf_token: $('#csrf_token').val()
                };

                $('#loading-spinner').show();
                $('#submit-register-btn').prop('disabled', true);
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: data,
                    success: function(response) {
                        $('#loading-spinner').hide();
                        $('#submit-register-btn').prop('disabled', false);
                        const data = JSON.parse(response);
                        showModal(data.success ? 'Éxito' : 'Error', data.message);
                        if (data.success) {
                            $('#feedbackModal').on('hidden.bs.modal', function () {
                                location.reload();
                            });
                        }
                    },
                    error: function() {
                        $('#loading-spinner').hide();
                        $('#submit-register-btn').prop('disabled', false);
                        showModal('Error', 'Error al procesar la solicitud.');
                    }
                });
            });

            // Manejar actualización de datos del usuario
            $('#save-user-btn').click(function() {
                const data = {
                    action: 'update_user',
                    nombre: $('#edit-nombre').val(),
                    email: $('#edit-email').val(),
                    telefono: $('#edit-telefono').val(),
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                };

                $('#loading-spinner').show();
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: data,
                    success: function(response) {
                        $('#loading-spinner').hide();
                        const data = JSON.parse(response);
                        showModal(data.success ? 'Éxito' : 'Error', data.message);
                        if (data.success) {
                            $('#editUserModal').modal('hide');
                            location.reload();
                        }
                    },
                    error: function() {
                        $('#loading-spinner').hide();
                        showModal('Error', 'Error al actualizar los datos.');
                    }
                });
            });

            // Manejar logout
            $('#logout-link').click(function(e) {
                e.preventDefault();
                const formData = new FormData();
                formData.append('action', 'logout');
                formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

                $('#loading-spinner').show();
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#loading-spinner').hide();
                        const data = JSON.parse(response);
                        showModal(data.success ? 'Éxito' : 'Error', data.message);
                        if (data.success) {
                            location.reload();
                        }
                    },
                    error: function() {
                        $('#loading-spinner').hide();
                        showModal('Error', 'Error al cerrar sesión.');
                    }
                });
            });
        });
    </script>
</body>
</html>