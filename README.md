# Randomix

Aplicación web desarrollada en PHP para realizar selecciones aleatorias de estudiantes a partir de archivos Excel. Su objetivo es facilitar la validación de listados, la selección de registros sin reemplazo y la generación de resultados en formatos Excel y PDF.

## Objetivo del proyecto

Organizar y automatizar el proceso de selección de estudiantes mediante una aplicación web con acceso administrativo, validación de datos y gestión temporal de archivos.

Randomix está diseñado para ejecutarse inicialmente en un entorno local con XAMPP y contempla su posterior despliegue en la infraestructura institucional.

## Funcionalidades del sistema

### Funcionalidades en desarrollo

- Acceso administrativo sin registro público.
- Importación de archivos Excel en formatos XLS y XLSX.
- Selección de la hoja de cálculo que se desea procesar.
- Validación de encabezados y registros.
- Selección aleatoria de estudiantes sin reemplazo.
- Generación de archivos Excel y PDF con los registros seleccionados y los restantes.
- Eliminación de archivos temporales y resultados vencidos.

### Reglas de procesamiento previstas

- Tamaño máximo del archivo: 20 MB.
- Máximo de 20.000 registros no vacíos.
- Validación de los 20 encabezados obligatorios.
- Rechazo de columnas adicionales.
- Ignorar filas completamente vacías y conservar las filas parcialmente diligenciadas.
- Tratar cada fila como un registro independiente, incluso cuando existan filas idénticas.
- Generar los documentos PDF en formato A3 horizontal.
- Mantener los archivos de resultados durante 15 minutos y eliminar los archivos vencidos.
- No almacenar permanentemente los datos personales de los estudiantes ni el historial de selecciones.

> **Estado actual:** el proyecto cuenta con una estructura inicial en PHP, configuración mediante variables de entorno, conexión PDO funcional con MariaDB y componentes iniciales para la gestión de sesiones. El flujo completo de autenticación, importación, selección y exportación todavía debe integrarse y validarse.

## Tecnologías utilizadas

- **PHP 8.2:** lógica del servidor.
- **Apache:** servidor web proporcionado por XAMPP.
- **MariaDB/MySQL:** almacenamiento de la cuenta administrativa.
- **Composer:** gestión de dependencias y carga automática de clases.
- **PDO:** conexión con la base de datos.
- **vlucas/phpdotenv:** lectura de variables de entorno.
- **HTML, CSS y JavaScript:** tecnologías previstas para construir la interfaz web.

## Requisitos previos

Para ejecutar el proyecto localmente se necesita:

- Windows con XAMPP instalado.
- Apache y MySQL/MariaDB habilitados desde el panel de XAMPP.
- PHP 8.2 o una versión compatible con las dependencias del proyecto.
- Composer.
- Git, si se va a clonar el repositorio.

El entorno utilizado durante el desarrollo incluye PHP 8.2.12, Apache 2.4.58 y MariaDB 10.4.32.

## Instalación y ejecución local

### 1. Clonar el repositorio

Abre PowerShell y ejecuta:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/Manvepa/Randomix.git
cd Randomix
```

Si ya tienes el proyecto descargado, no necesitas clonarlo de nuevo. En ese caso, entra directamente a su carpeta:

```powershell
cd C:\xampp\htdocs\Randomix
```

### 2. Iniciar XAMPP

Abre el panel de control de XAMPP e inicia los servicios:

- Apache.
- MySQL.

Comprueba que ambos servicios estén funcionando correctamente.

### 3. Instalar las dependencias

Desde la carpeta del proyecto, ejecuta Composer utilizando el PHP de XAMPP:

```powershell
& "C:\xampp\php\php.exe" "C:\Users\Manu\.config\herd-lite\bin\composer.phar" install
```

La ruta del archivo `composer.phar` corresponde al entorno de desarrollo utilizado durante la creación del proyecto. Si instalas Randomix en otro equipo, utiliza la ruta de Composer disponible en ese equipo.

Este comando instala las dependencias declaradas en `composer.lock` y genera el autoloader de clases.

### 4. Configurar las variables de entorno

Crea el archivo `.env` en la raíz del proyecto a partir de `.env.example`.

Configura los valores correspondientes a tu instalación local:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/Randomix

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=randomix
DB_USERNAME=root
DB_PASSWORD=
```

**Importante:** este ejemplo supone que MariaDB acepta conexiones locales con el usuario `root` sin contraseña. Si tu instalación tiene una contraseña configurada, utiliza la contraseña local correspondiente.

No compartas ni subas el archivo `.env` a Git. Para un despliegue institucional, utiliza una configuración de producción y un usuario de base de datos con privilegios mínimos.

### 5. Crear la base de datos

Desde phpMyAdmin o la consola de MariaDB, crea la base de datos si todavía no existe:

```sql
CREATE DATABASE randomix
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

La aplicación utiliza la tabla `admin_users` para almacenar las cuentas administrativas. En el entorno de desarrollo, esta tabla ya fue creada.

Si estás preparando una instalación nueva, también debes crear la tabla con la estructura definida por el proyecto antes de ejecutar las pruebas de conexión:

```sql
CREATE TABLE admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

La base de datos no debe utilizarse para almacenar permanentemente los listados de estudiantes ni el historial de selecciones.

### 6. Ejecutar las pruebas disponibles

Desde la raíz del proyecto, comprueba primero la conexión con la base de datos:

```powershell
& "C:\xampp\php\php.exe" tests\test_database_connection.php
```

Después, comprueba la gestión de sesiones:

```powershell
& "C:\xampp\php\php.exe" tests\test_session_manager.php
```

Para validar la sintaxis de los archivos PHP, puedes ejecutar:

```powershell
& "C:\xampp\php\php.exe" -l public\index.php
& "C:\xampp\php\php.exe" -l src\Support\Database.php
& "C:\xampp\php\php.exe" -l src\Security\SessionManager.php
```

Estas pruebas verifican componentes individuales; no significan que el flujo completo de la aplicación esté terminado.

### 7. Abrir la aplicación

Con Apache iniciado, abre en el navegador:

`http://localhost/Randomix/public/`

Esta dirección corresponde a la estructura actual del proyecto cuando se accede mediante el directorio `htdocs` de XAMPP. La configuración definitiva de rutas y del acceso mediante un dominio local se ajustará durante la integración de la aplicación.

En el estado actual, el punto de entrada presenta una respuesta básica de verificación. La interfaz completa y el flujo funcional se incorporarán progresivamente.

## Estructura del proyecto

```text
Randomix/
├── config/                 # Configuración de la aplicación y la BD
├── database/
│   └── migrations/         # Cambios en el esquema de la BD
├── public/                 # Punto de entrada y recursos públicos
├── src/
│   ├── Controllers/        # Controladores
│   ├── Export/              # Generación de resultados
│   ├── Import/              # Procesamiento de archivos Excel
│   ├── Repositories/       # Acceso a datos
│   ├── Security/            # Sesiones y seguridad
│   ├── Services/            # Lógica de negocio
│   ├── Support/             # Utilidades compartidas
│   └── Validators/          # Validaciones
├── storage/
│   ├── logs/                # Registros de la aplicación
│   └── temporary/           # Archivos temporales privados
├── templates/
│   ├── auth/                # Vistas de autenticación
│   ├── layout/              # Plantillas compartidas
│   └── selection/           # Vistas de selección
├── tests/                   # Pruebas
├── .env.example             # Ejemplo de configuración
├── .gitignore
├── composer.json
└── public/index.php
```

## Seguridad y protección de datos

- No subir archivos Excel con información personal de estudiantes.
- No subir contraseñas, credenciales ni archivos `.env` reales.
- No versionar los archivos generados ni los resultados de las selecciones.
- Mantener los archivos temporales fuera del directorio público.
- Almacenar contraseñas administrativas mediante funciones de hash seguras.
- Utilizar consultas preparadas para el acceso a la base de datos.
- Configurar HTTPS y privilegios mínimos de base de datos antes del despliegue institucional.

## Estado del desarrollo

Randomix se encuentra en desarrollo incremental. La estructura del proyecto, la configuración de entorno, la conexión PDO y las pruebas iniciales de sesión están preparadas. La interfaz visual, la autenticación completa y el procesamiento integral de Excel y PDF deben integrarse y probarse antes de considerar el sistema listo para producción.

## Licencia

Proyecto de uso institucional. Los términos de distribución y uso deberán definirse según las condiciones de la organización responsable.
