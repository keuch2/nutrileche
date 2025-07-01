# Nutrileche 2025 - Plataforma de Concurso

Sistema de gestión para el concurso Nutrileche 2025, que permite a los participantes registrarse, iniciar sesión y subir evidencias de sus proyectos.

## Características

- **Registro de usuarios**: Los participantes pueden registrarse seleccionando su departamento, institución educativa y datos personales.
- **Autenticación con OTP**: Sistema de inicio de sesión seguro mediante códigos de un solo uso enviados por email.
- **Carga de evidencias**: Los usuarios pueden subir diferentes tipos de archivos como proyectos, redacciones, fotos y videos.
- **Panel de administración**: Gestión completa de usuarios, departamentos y evidencias.
- **Gestión dinámica de departamentos**: Los administradores pueden añadir y configurar permisos por departamento.
- **Descarga de evidencias**: Posibilidad de descargar evidencias por usuario o por departamento en formato ZIP.

## Requisitos

- PHP 7.4 o superior
- MySQL/MariaDB
- Servidor web (Apache, Nginx)
- Extensiones PHP: PDO, ZipArchive, Fileinfo

## Instalación

1. Clonar el repositorio en el directorio web
2. Crear una base de datos MySQL
3. Importar la estructura de la base de datos desde el archivo `database.sql` (si está disponible)
4. Configurar los parámetros de conexión a la base de datos en `index.php` y `admin.php`
5. Asegurar que el directorio `uploads` tenga permisos de escritura

## Acceso al panel de administración

- URL: `/admin.php`
- Usuario: admin
- Contraseña: capainlac2025

## Estructura del proyecto

- `index.php`: Frontend para participantes
- `admin.php`: Panel de administración
- `assets/`: Archivos CSS, JS y multimedia
- `uploads/`: Directorio para archivos subidos por los usuarios
- `PHPMailer/`: Biblioteca para envío de emails

## Licencia

Todos los derechos reservados. 