# Randomix

Randomix es una aplicación web desarrollada en PHP para realizar selecciones aleatorias de estudiantes a partir de archivos Excel. Su propósito es facilitar la importación y validación de listados, la selección de registros sin reemplazo y la generación de resultados en formatos Excel y PDF.

El proyecto está orientado al uso institucional y se desarrolla de forma incremental, con una arquitectura modular, una interfaz administrativa y mecanismos de protección para el manejo temporal de los datos.

## Objetivo del proyecto

Organizar y automatizar los procesos de selección aleatoria de estudiantes mediante una aplicación web que permita:

- Acceder a las herramientas mediante una interfaz administrativa.
- Importar archivos Excel y seleccionar la hoja que se desea procesar.
- Validar los encabezados y la estructura del archivo.
- Seleccionar aleatoriamente una cantidad determinada de estudiantes sin reemplazo.
- Consultar y exportar los registros seleccionados y los no seleccionados.
- Gestionar temporalmente los archivos y resultados generados.
- Evitar el almacenamiento permanente de los listados de estudiantes y del historial de selecciones.

El entorno principal de desarrollo es Windows con XAMPP. El proyecto contempla su posterior despliegue en la infraestructura institucional.

## Funcionalidades del sistema

### Funcionalidades implementadas

- **Interfaz administrativa:** pantalla de acceso y panel principal con diseño adaptable.
- **Autenticación:** acceso administrativo, gestión de sesiones y cierre de sesión.
- **Protección de formularios:** uso de tokens CSRF en los formularios de autenticación y cierre de sesión.
- **Configuración centralizada:** separación de la configuración de la aplicación, la base de datos y las reglas de importación.
- **Conexión a la base de datos:** acceso mediante PDO a MariaDB/MySQL.
- **Carga de archivos Excel:** recepción de archivos XLS y XLSX.
- **Validación de archivos:** comprobación del archivo recibido, sus características y las restricciones de carga.
- **Selección de hoja:** identificación de las hojas disponibles en el libro de Excel.
- **Validación de encabezados:** comprobación de los encabezados obligatorios, los faltantes, los adicionales y los duplicados.
- **Almacenamiento temporal:** conservación de los archivos cargados fuera del directorio público.
- **Expiración de archivos:** eliminación de archivos temporales vencidos según el tiempo de retención configurado.
- **Identidad visual:** logotipo de Randomix en las pantallas de acceso y en el encabezado del panel, además de la tarjeta de bienvenida.
- **Pruebas individuales:** pruebas disponibles para la conexión a la base de datos, las sesiones, la validación de archivos y la lectura y validación de encabezados de Excel.

### Funcionalidades pendientes de integración y validación

- Procesamiento completo de los registros de estudiantes.
- Validación integral de los límites de registros y de las reglas de procesamiento.
- Selección aleatoria de una cantidad determinada de estudiantes sin reemplazo.
- Presentación de los registros seleccionados y no seleccionados.
- Exportación de ambos grupos a Excel y PDF.
- Generación de documentos PDF en formato A3 horizontal.
- Verificación integral de la expiración y eliminación de archivos temporales y resultados generados.
- Pruebas de integración del flujo completo, desde la carga hasta la exportación.

Estas funcionalidades forman parte del objetivo del proyecto, pero deben considerarse terminadas únicamente después de implementar y validar su funcionamiento integral.

## Reglas de procesamiento

Las siguientes reglas definen el comportamiento previsto para el procesamiento de los archivos:

| Regla | Descripción |
|---|---|
| Formatos permitidos | XLS y XLSX |
| Tamaño máximo | 20 MB por archivo |
| Máximo de registros | 20.000 registros no vacíos |
| Encabezados | 20 encabezados obligatorios |
| Columnas adicionales | Rechazar archivos que contengan columnas no permitidas |
| Filas vacías | Ignorar las filas completamente vacías |
| Filas parcialmente diligenciadas | Conservarlas como registros independientes |
| Registros idénticos | No eliminar duplicados; cada fila cuenta como un registro independiente |
| Selección | Aleatoria y sin reemplazo |
| Formato de exportación | Excel y PDF |
| Orientación del PDF | A3 horizontal |
| Retención temporal | 15 minutos |
| Persistencia de datos | No conservar permanentemente los listados ni el historial de selecciones |

La implementación debe mantener estas reglas centralizadas en la configuración correspondiente y validarlas durante el procesamiento.

### Encabezados obligatorios

El archivo Excel debe contener los siguientes 20 encabezados:

1. `ESTADO_ALUMNO`
2. `DANE`
3. `INSTITUCION`
4. `SEDE`
5. `GRADO`
6. `JORNADA`
7. `tipo_doc`
8. `doc`
9. `apellido_1`
10. `apellido_2`
11. `nombre_1`
12. `nombre_2`
13. `DIRECCION`
14. `edad`
15. `FECHA_INGRESO AL MUNICIPIO`
16. `TELEFONO`
17. `BARRIO`
18. `ACUDIENTE Tipo Doc`
19. `ACUDIENTE Doc`
20. `ACUDIENTES`

La validación debe realizarse sobre los encabezados de la hoja seleccionada y respetar las reglas definidas para los nombres, las columnas adicionales y los encabezados duplicados.

## Tecnologías utilizadas

| Tecnología | Uso |
|---|---|
| PHP 8.2 | Lógica del servidor y procesamiento de solicitudes |
| Apache | Servidor web mediante XAMPP |
| MariaDB/MySQL | Almacenamiento de las cuentas administrativas |
| PDO | Acceso a la base de datos mediante consultas preparadas |
| Composer | Gestión de dependencias y autoloading |
| `vlucas/phpdotenv` | Lectura de variables de entorno |
| PhpSpreadsheet | Lectura y procesamiento de archivos Excel |
| HTML | Estructura de las vistas |
| CSS | Diseño de la interfaz y adaptación a diferentes pantallas |
| JavaScript | Complementos de interacción cuando corresponda |

## Arquitectura del proyecto

Randomix utiliza una arquitectura modular en PHP que separa las responsabilidades de presentación, control, lógica de negocio, acceso a datos, seguridad y procesamiento de archivos.

Las principales responsabilidades son:

- **Controllers:** reciben las solicitudes y coordinan las operaciones.
- **Services:** contienen la lógica de negocio.
- **Validators:** validan archivos y estructuras de datos.
- **Import:** concentra la lectura y el procesamiento de archivos Excel.
- **Security:** gestiona las sesiones y los mecanismos de protección.
- **Support:** proporciona utilidades compartidas, incluida la conexión a la base de datos.
- **Templates:** contiene las vistas y los componentes visuales.
- **Config:** centraliza los parámetros de funcionamiento.
- **Tests:** contiene las pruebas automatizadas o ejecutables disponibles.

Esta separación facilita el mantenimiento, las pruebas y la incorporación progresiva de las funcionalidades pendientes.

## Requisitos previos

Para ejecutar Randomix localmente se necesita:

- Windows con XAMPP instalado.
- Apache y MySQL/MariaDB habilitados desde el panel de XAMPP.
- PHP 8.2 compatible con las dependencias instaladas.
- Composer.
- Git, si se desea clonar el repositorio.

El entorno utilizado durante el desarrollo incluye:

- PHP 8.2.12.
- Apache 2.4.58.
- MariaDB 10.4.32.
- Composer 2.10.3.
- PhpSpreadsheet 5.10.0.

Las versiones disponibles pueden variar según el equipo. Antes de ejecutar la aplicación, comprueba las versiones efectivamente instaladas.

## Instalación y ejecución local

### 1. Obtener el repositorio

Abre PowerShell y ejecuta:

```powershell
Set-Location "C:\xampp\htdocs"

git clone https://github.com/Manvepa/Randomix.git

Set-Location "C:\xampp\htdocs\Randomix"
```

Si el proyecto ya está descargado, no necesitas clonarlo nuevamente:

```powershell
Set-Location "C:\xampp\htdocs\Randomix"
```

### 2. Iniciar XAMPP

Abre el panel de control de XAMPP e inicia:

- Apache.
- MySQL.

Comprueba que ambos servicios estén funcionando correctamente antes de continuar.

### 3. Instalar las dependencias

Desde la raíz del proyecto, ejecuta:

```powershell
composer install
```

Si necesitas utilizar explícitamente el PHP de XAMPP, puedes ejecutar Composer con el archivo `composer.phar` disponible en tu instalación:

```powershell
& "C:\xampp\php\php.exe" "RUTA\A\composer.phar" install
```

Reemplaza `RUTA\A\composer.phar` por la ruta real de Composer en tu equipo.

El comando instala las dependencias declaradas en `composer.lock` y genera el autoloader utilizado por la aplicación.

Si el proyecto ya cuenta con las dependencias instaladas, no es necesario reinstalarlas en cada ejecución.

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

Este ejemplo supone que MariaDB acepta conexiones locales con el usuario `root` sin contraseña. Si tu instalación utiliza otra configuración, debes establecer los valores correspondientes.

**Importante:** no subas el archivo `.env` a Git ni compartas sus credenciales. Para un despliegue institucional, configura los parámetros de producción, desactiva la depuración y utiliza un usuario de base de datos con privilegios mínimos.

### 5. Crear y preparar la base de datos

Si la base de datos todavía no existe, créala desde phpMyAdmin o la consola de MariaDB:

```sql
CREATE DATABASE randomix
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

La aplicación utiliza la tabla `admin_users` para almacenar las cuentas administrativas.

Si estás preparando una instalación nueva, crea la tabla con la estructura compatible con el proyecto:

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

Antes de crear la tabla, comprueba si ya existe. Si el proyecto dispone de migraciones o de un mecanismo de inicialización actualizado, utiliza la estructura definida allí para evitar inconsistencias.

La cuenta administrativa debe crearse mediante el mecanismo previsto por el proyecto. No insertes contraseñas en texto plano en la base de datos: utiliza hashes seguros compatibles con PHP.

La base de datos no debe utilizarse para almacenar permanentemente los listados de estudiantes ni el historial de selecciones.

### 6. Ejecutar las pruebas disponibles

Desde la raíz del proyecto, comprueba la conexión a la base de datos:

```powershell
& "C:\xampp\php\php.exe" tests\test_database_connection.php
```

Comprueba la gestión de sesiones:

```powershell
& "C:\xampp\php\php.exe" tests\test_session_manager.php
```

También están disponibles pruebas para la validación de encabezados, la lectura de archivos Excel y la validación de cargas. Puedes ejecutarlas individualmente con el PHP de XAMPP:

```powershell
& "C:\xampp\php\php.exe" tests\test_excel_headers_validator.php

& "C:\xampp\php\php.exe" tests\test_excel_reader.php

& "C:\xampp\php\php.exe" tests\test_file_upload_validator.php
```

Para comprobar la sintaxis de archivos PHP concretos:

```powershell
& "C:\xampp\php\php.exe" -l public\index.php

& "C:\xampp\php\php.exe" -l src\Support\Database.php

& "C:\xampp\php\php.exe" -l src\Security\SessionManager.php
```

Una prueba individual satisfactoria confirma únicamente el comportamiento que evalúa. No sustituye las pruebas de integración del flujo completo de Randomix.

### 7. Abrir la aplicación

Con Apache iniciado, abre en el navegador la ruta local configurada para el proyecto.

Si el directorio público se utiliza como punto de entrada, la URL puede ser:

```text
http://localhost/Randomix/public/
```

Si el proyecto está configurado para exponer `public` mediante una ruta raíz o un host virtual, utiliza la URL correspondiente a esa configuración.

El acceso correcto depende de la configuración de Apache y de cómo se publiquen los recursos del directorio `public`.

## Estructura del proyecto

La estructura general del proyecto es la siguiente. Algunos directorios pueden incorporar nuevos componentes conforme avance el desarrollo.

```text
Randomix/
├── config/
│   ├── app.php
│   ├── database.php
│   └── import.php
├── database/
│   └── migrations/
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   └── app.css
│   │   └── images/
│   │       └── randomix-logo.png
│   └── index.php
├── src/
│   ├── Controllers/
│   ├── Import/
│   │   └── ExcelReader.php
│   ├── Repositories/
│   ├── Security/
│   │   └── SessionManager.php
│   ├── Services/
│   ├── Support/
│   │   └── Database.php
│   └── Validators/
├── storage/
│   ├── logs/
│   ├── temporary/
│   └── ...
├── templates/
│   ├── auth/
│   │   └── login.php
│   ├── layout/
│   │   ├── app.php
│   │   ├── header.php
│   │   └── footer.php
│   └── selection/
│       └── index.php
├── tests/
├── .env.example
├── .gitignore
├── composer.json
├── composer.lock
└── README.md
```

Los directorios `Repositories`, `Export` y otros componentes pueden contener código en desarrollo o incorporarse conforme se complete la arquitectura. La estructura mostrada es orientativa: el contenido real del repositorio es la referencia definitiva.

## Gestión temporal de archivos

Los archivos cargados se almacenan en `storage/temporary`, fuera del directorio público, para evitar que los documentos queden expuestos mediante una URL directa.

El mecanismo implementado contempla:

- Nombres de archivo generados para el almacenamiento temporal.
- Restricciones de carga y comprobaciones de duplicados mediante SHA-256.
- Registro de metadatos de los archivos cargados.
- Retención temporal de 15 minutos.
- Eliminación de archivos vencidos mediante el mecanismo de limpieza correspondiente.

La limpieza y la retención deben verificarse también para los resultados que se generen en las siguientes fases del proyecto.

## Interfaz de usuario

La interfaz está construida con PHP, HTML y CSS, y utiliza una presentación común para las páginas de la aplicación.

Actualmente incluye:

- Pantalla de acceso administrativo.
- Encabezado compartido para el panel.
- Panel de bienvenida.
- Interfaz para las operaciones de carga e inspección de archivos.
- Logotipo de Randomix en la pantalla de acceso y en el panel.
- Estilos adaptables a diferentes tamaños de pantalla.

Los recursos visuales se encuentran en `public/assets`, y las vistas PHP en `templates`.

La presencia de un elemento visual no implica que todas las operaciones del proceso de selección estén terminadas. Las funcionalidades deben validarse de manera independiente.

## Seguridad y protección de datos

Debido a que los archivos Excel pueden contener información personal de estudiantes, deben aplicarse las siguientes medidas:

- No subir archivos con información personal al repositorio.
- No versionar contraseñas, credenciales ni archivos `.env` reales.
- Mantener los archivos temporales fuera del directorio público.
- Utilizar hashes seguros para almacenar las contraseñas administrativas.
- Validar las entradas recibidas desde formularios y archivos.
- Utilizar consultas preparadas para las operaciones con la base de datos.
- Proteger las operaciones sensibles mediante controles de sesión y tokens CSRF.
- Restringir el acceso a las funciones administrativas.
- Eliminar los archivos temporales y resultados vencidos.
- Configurar HTTPS y privilegios mínimos de base de datos antes del despliegue institucional.
- Desactivar la depuración y revisar los permisos de archivos y directorios en producción.

El manejo temporal de archivos no elimina por sí solo todas las posibles copias de los datos. Antes del despliegue, también deben revisarse los registros, las copias de seguridad y los mecanismos de almacenamiento del servidor.

## Estado del desarrollo

Randomix se desarrolla por fases, validando cada componente antes de avanzar al siguiente.

**Implementado y probado parcialmente:**

- Estructura modular de la aplicación.
- Configuración de la aplicación y de la base de datos.
- Conexión PDO con MariaDB.
- Gestión de sesiones y flujo de acceso administrativo.
- Interfaz visual de acceso y panel.
- Carga y validación inicial de archivos Excel.
- Lectura de hojas y validación de encabezados.
- Almacenamiento temporal y mecanismos de expiración.
- Pruebas individuales de componentes.

**Pendiente de completar y validar integralmente:**

- Procesamiento de todos los registros según las reglas del proyecto.
- Selección aleatoria sin reemplazo.
- Presentación y consulta de los resultados.
- Exportación a Excel y PDF en formato A3 horizontal.
- Integración y pruebas del flujo completo.
- Preparación y validación del despliegue institucional.

El proyecto no debe considerarse listo para producción hasta completar las funcionalidades pendientes, ejecutar las pruebas de integración y revisar las medidas de seguridad y protección de datos.

## Licencia y uso

Randomix es un proyecto orientado al uso institucional. Los términos de distribución, modificación y uso deberán definirse de acuerdo con las condiciones de la organización responsable.
