# Randomix

Randomix es una aplicación web desarrollada en PHP para importar listados de estudiantes desde archivos Excel, realizar una selección aleatoria sin reemplazo y consultar o exportar los registros seleccionados y no seleccionados.

El proyecto está orientado al uso institucional y se desarrolla en Windows con el entorno XAMPP existente. Los archivos de trabajo y los resultados se manejan temporalmente; antes de desplegar en producción se deben validar la configuración, la seguridad y las políticas institucionales de protección de datos.

> **Estado actual:** están implementados la importación y validación de Excel, el procesamiento de registros, la selección aleatoria, la exportación a Excel de ambos grupos y la vista previa PDF en formato A3 horizontal.

## Tabla de contenido

- [Objetivo](#objetivo)
- [Funcionalidades](#funcionalidades)
- [Reglas de importación y selección](#reglas-de-importación-y-selección)
- [Encabezados requeridos](#encabezados-requeridos)
- [Tecnologías y dependencias](#tecnologías-y-dependencias)
- [Requisitos previos](#requisitos-previos)
- [Instalación local con XAMPP](#instalación-local-con-xampp)
- [Uso de la aplicación](#uso-de-la-aplicación)
- [Exportaciones](#exportaciones)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Pruebas y validaciones](#pruebas-y-validaciones)
- [Seguridad y protección de datos](#seguridad-y-protección-de-datos)
- [Solución de problemas](#solución-de-problemas)
- [Estado y siguientes pasos](#estado-y-siguientes-pasos)
- [Licencia y uso](#licencia-y-uso)

## Objetivo

Facilitar la selección aleatoria de estudiantes a partir de una hoja de cálculo institucional, conservando los encabezados y el orden de las columnas originales. La aplicación separa los registros en dos grupos:

- **Seleccionados:** registros elegidos aleatoriamente.
- **No seleccionados:** registros que no fueron elegidos.

La selección se realiza sin reemplazo: en una ejecución, un mismo registro no se asigna a ambos grupos.

## Funcionalidades

### Acceso y panel administrativo

- Pantalla de acceso y panel principal.
- Gestión de sesión y cierre de sesión.
- Protección CSRF para operaciones protegidas por formularios.
- Interfaz compartida y estilos adaptables.

### Importación y procesamiento de Excel

- Admite archivos `.xls` y `.xlsx`.
- Permite seleccionar la hoja que se procesará.
- Valida los encabezados y la estructura del archivo.
- Aplica las restricciones de carga configuradas por la aplicación.
- Guarda los archivos de trabajo en `storage/temporary/`, fuera del directorio público.
- Detecta archivos duplicados mediante SHA-256.
- Ignora las filas completamente vacías y conserva las parcialmente diligenciadas.
- Conserva filas duplicadas como registros independientes.
- Limita el procesamiento a 20.000 registros no vacíos.

### Selección y resultados

- Selecciona aleatoriamente la cantidad solicitada, sin reemplazo.
- Muestra el total procesado y los totales de seleccionados y no seleccionados.
- Presenta ambos grupos en el panel con los encabezados originales.
- Permite solicitar cero seleccionados, siempre que se cumplan las demás validaciones.

### Exportación de resultados

- Genera un archivo Excel para los seleccionados.
- Genera un archivo Excel para los no seleccionados.
- Permite visualizar los seleccionados en PDF.
- Permite visualizar los no seleccionados en PDF.
- Los PDF usan formato A3 horizontal y se abren en una nueva pestaña.

## Reglas de importación y selección

| Regla | Comportamiento |
|---|---|
| Formatos admitidos | `.xls` y `.xlsx` |
| Tamaño máximo | 20 MB por archivo |
| Máximo de registros | 20.000 registros no vacíos |
| Encabezados | Deben coincidir con los requeridos por el proyecto |
| Columnas adicionales | Se rechazan cuando incumplen la validación estricta configurada |
| Filas completamente vacías | Se ignoran |
| Filas parcialmente diligenciadas | Se conservan |
| Filas duplicadas | Se conservan como registros independientes |
| Selección | Aleatoria y sin reemplazo |
| Cantidad solicitada | No puede ser negativa ni superar el total disponible |
| Exportación Excel | Un archivo por cada grupo |
| Vista previa PDF | Un documento por grupo, en A3 horizontal |
| Retención temporal | 15 minutos, según la configuración del proyecto |

Los límites y las reglas pueden variar si se modifica la configuración. Verifica el código vigente antes de cambiar los valores documentados.

## Encabezados requeridos

El archivo de entrada debe contener estos 20 encabezados, respetando sus nombres:

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

Antes de procesar un archivo real, confirma que los encabezados coincidan con los que valida la versión instalada. No renombres columnas sin actualizar también su validador.

## Tecnologías y dependencias

| Tecnología o paquete | Uso |
|---|---|
| PHP 8.2 o compatible con `composer.json` | Lógica del servidor y procesamiento de solicitudes |
| Apache mediante XAMPP | Servidor web local |
| MariaDB/MySQL | Datos administrativos, si así lo requiere la configuración activa |
| PDO | Acceso a base de datos |
| Composer | Dependencias y autoloading |
| `vlucas/phpdotenv` | Lectura de variables de entorno |
| PhpSpreadsheet | Lectura y generación de Excel |
| `tecnickcom/tc-lib-pdf` | Generación de PDF |
| HTML, CSS y JavaScript | Interfaz de usuario |

Las versiones exactas están determinadas por `composer.lock` y por el PHP que se ejecute en el equipo.

## Requisitos previos

- Windows con XAMPP instalado.
- Apache iniciado desde XAMPP.
- MySQL/MariaDB iniciado si la configuración activa necesita la base de datos.
- PHP compatible con las dependencias de Composer.
- Composer.
- Git, si se va a clonar el repositorio.

Ruta de desarrollo utilizada:

```text
C:\xampp\htdocs\Randomix
```

**El entorno local documentado usa el XAMPP existente; no requiere Docker.**

## Instalación local con XAMPP

### 1. Obtener el repositorio

Si todavía no tienes el repositorio:

```powershell
Set-Location "C:\xampp\htdocs"
git clone https://github.com/Manvepa/Randomix.git
Set-Location "C:\xampp\htdocs\Randomix"
```

Si ya está clonado, entra a la carpeta existente:

```powershell
Set-Location "C:\xampp\htdocs\Randomix"
```

### 2. Iniciar XAMPP

Abre XAMPP Control Panel e inicia Apache. Inicia MySQL si la configuración de Randomix utiliza la base de datos.

### 3. Instalar dependencias

Desde la raíz del proyecto:

```powershell
composer install
```

Para comprobar la versión de PHP de XAMPP:

```powershell
C:\xampp\php\php.exe -v
```

Si Composer está asociado a otra versión de PHP y necesitas ejecutar la instalación con PHP de XAMPP, utiliza la ruta real de `composer.phar` en tu equipo:

```powershell
& "C:\xampp\php\php.exe" "RUTA\REAL\composer.phar" install
```

Sustituye `RUTA\REAL\composer.phar` por la ruta correcta. `composer install` utiliza las versiones fijadas en `composer.lock` y genera el autoloader.

### 4. Configurar el entorno

Revisa `.env.example` y los archivos de configuración para confirmar qué variables consume la versión actual. Si la aplicación usa `.env`, crea el archivo local a partir del ejemplo y completa los valores correctos para tu instalación.

Ejemplo orientativo —conserva únicamente las variables realmente utilizadas por el código—:

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

Los valores de base de datos son ilustrativos; pueden diferir de tu instalación. No subas `.env` a Git ni compartas credenciales.

### 5. Preparar la base de datos, si aplica

Antes de crear tablas, revisa `config/database.php`, el directorio `database/` y cualquier mecanismo de inicialización o migración existente. Si la aplicación requiere una base de datos para las cuentas administrativas, crea la base de datos con el nombre configurado y utiliza el esquema que corresponda al código actual.

No crees tablas manualmente a partir de ejemplos antiguos sin verificar primero el esquema vigente. Las contraseñas deben almacenarse mediante hashes seguros, nunca en texto plano.

### 6. Abrir la aplicación

Con Apache activo, la URL local puede ser:

```text
http://localhost/Randomix/public/
```

La dirección exacta depende de la configuración de Apache y del punto de entrada. Si se utiliza un host virtual o se publica `public/` como raíz del sitio, utiliza la URL correspondiente.

## Uso de la aplicación

1. Abre Randomix en el navegador e inicia sesión con una cuenta administrativa válida para tu instalación.
2. Carga un archivo `.xls` o `.xlsx` dentro de los límites permitidos.
3. Selecciona la hoja que contiene el listado.
4. Ejecuta la validación y confirma que los encabezados sean correctos.
5. Indica la cantidad de estudiantes que deseas seleccionar.
6. Ejecuta la selección aleatoria.
7. Revisa el total procesado y las tablas de seleccionados y no seleccionados.
8. Utiliza los enlaces correspondientes para exportar a Excel o visualizar el PDF.

La selección produce ambos grupos en una misma ejecución. Utiliza los resultados ya generados para exportar a distintos formatos, mientras sigan disponibles en la sesión; no vuelvas a seleccionar solo para cambiar el formato de salida.

## Exportaciones

### Excel

Se generan archivos `.xlsx` separados para seleccionados y no seleccionados. La exportación conserva el orden de los encabezados originales y aplica formato a la fila de encabezados. El servicio contempla valores que podrían interpretarse como fórmulas y campos que deben conservarse como texto, como códigos con ceros iniciales.

### PDF

Los enlaces generan una vista previa en el navegador mediante una respuesta `Content-Disposition: inline`. Hay un PDF para cada grupo.

Características de la vista previa:

- Formato A3 horizontal.
- Tabla con bordes.
- Encabezados repetidos en las páginas nuevas.
- Altura de fila calculada según la longitud del texto.
- Evita comenzar una fila si no cabe completa en el espacio disponible de la página.
- El contenido se genera para responder al navegador y no se guarda como una exportación permanente en el directorio público.

La exportación Excel y la vista previa PDF son funciones separadas.

## Estructura del proyecto

La siguiente estructura es orientativa. Confirma los nombres y directorios exactos en el repositorio actual, ya que pueden evolucionar durante el desarrollo.

```text
Randomix/
├── config/                  # Configuración de aplicación, base de datos e importación
├── database/                # Migraciones o recursos de base de datos, si existen
├── public/
│   ├── assets/              # CSS, imágenes y recursos públicos
│   └── index.php            # Punto de entrada web
├── src/
│   ├── Controllers/         # Coordinación de solicitudes y respuestas
│   ├── Import/              # Lectura y procesamiento de Excel
│   ├── Repositories/        # Acceso a datos, si aplica
│   ├── Security/            # Sesiones y protección
│   ├── Services/            # Lógica de negocio y exportaciones
│   ├── Support/             # Utilidades compartidas
│   └── Validators/          # Validación de archivos y datos
├── storage/
│   ├── logs/                # Registros, si están habilitados
│   └── temporary/           # Archivos temporales de trabajo
├── templates/
│   ├── auth/                # Vistas de acceso
│   ├── layout/              # Plantilla compartida
│   └── selection/           # Vista de carga y resultados
├── tests/                   # Pruebas y scripts de validación
├── .env.example             # Ejemplo de configuración
├── .gitignore
├── composer.json
├── composer.lock
└── README.md
```

El autoload PSR-4 está definido en `composer.json`. La estructura real del código es la referencia definitiva.

## Pruebas y validaciones

Ejecuta los comandos desde `C:\xampp\htdocs\Randomix`. Los ejemplos utilizan explícitamente el PHP de XAMPP para evitar confusiones con otra versión instalada en Windows.

### Comprobar sintaxis PHP

```powershell
C:\xampp\php\php.exe -l .\public\index.php
C:\xampp\php\php.exe -l .\src\Services\PdfPreviewService.php
C:\xampp\php\php.exe -l .\src\Services\ExcelExportService.php
C:\xampp\php\php.exe -l .\templates\selection\index.php
```

Si un archivo cambió de ubicación o nombre, ajusta el comando al archivo existente.

### Comprobar la disponibilidad de tc-lib-pdf

```powershell
C:\xampp\php\php.exe -r "require 'vendor/autoload.php'; echo class_exists('\\Com\\Tecnick\\Pdf\\Tcpdf') ? 'OK: tc-lib-pdf disponible' : 'ERROR: clase no encontrada';"
```

La integración local utiliza fuentes generadas en:

```text
vendor/tecnickcom/tc-lib-pdf-font/target/fonts
```

Si la carpeta no existe después de instalar dependencias, consulta las instrucciones de construcción de fuentes del paquete `tecnickcom/tc-lib-pdf-font`. No repitas ese proceso si las fuentes ya están disponibles.

### Casos funcionales recomendados

- Archivo Excel válido y selección de hoja.
- Encabezados faltantes, adicionales o duplicados.
- Archivo sin registros válidos.
- Filas vacías y parcialmente diligenciadas.
- Cantidad seleccionada igual a cero.
- Cantidad solicitada superior al total disponible.
- Exportación Excel de ambos grupos.
- Vista previa PDF de ambos grupos, incluidos textos largos y documentos de varias páginas.
- Acceso a las rutas de exportación sin sesión válida.
- Expiración y limpieza de archivos temporales.

El directorio `tests/` contiene pruebas y scripts específicos. Revisa el propósito de cada archivo antes de ejecutarlo; una prueba aislada no sustituye una prueba de integración del flujo completo.

## Seguridad y protección de datos

Los archivos pueden contener datos personales de estudiantes. Antes de utilizar datos reales:

- No subas listados, documentos exportados ni información personal al repositorio.
- No versionas `.env` real, contraseñas ni credenciales.
- Mantén los archivos temporales fuera del directorio público.
- Usa privilegios mínimos para las cuentas de base de datos.
- Mantén habilitadas la autenticación, las sesiones y la protección CSRF.
- Valida los datos de entrada y usa consultas preparadas para la base de datos.
- Almacena las contraseñas mediante hashes seguros.
- Comprueba la limpieza de archivos temporales y la expiración de resultados.
- En producción, desactiva la depuración, configura HTTPS y revisa los permisos de archivos y carpetas.
- Evita mostrar trazas o detalles internos de errores a usuarios finales.
- Revisa logs y copias de seguridad para evitar la retención innecesaria de información personal.

El almacenamiento temporal no garantiza por sí solo que no existan copias en otros lugares. El despliegue debe respetar las políticas institucionales de tratamiento de datos.

## Solución de problemas

### Composer instala dependencias, pero la aplicación no abre

Comprueba que se haya utilizado una versión compatible de PHP, que exista `vendor/autoload.php` y que las dependencias estén instaladas. Revisa el error concreto y los logs antes de cambiar paquetes.

### Falla la conexión a la base de datos

Comprueba que MySQL/MariaDB esté iniciado y que los valores configurados coincidan con tu instalación. No asumas que `root` siempre usa una contraseña vacía.

### No se genera el PDF

Verifica que `tecnickcom/tc-lib-pdf` esté instalado, que el autoloader exista y que esté presente el directorio de fuentes requerido. Consulta los logs del servidor para el detalle técnico y evita mostrar información interna al usuario final.

### El PDF muestra texto superpuesto o páginas incorrectas

Comprueba que se esté usando la versión actual de `PdfPreviewService.php`, incluido el cálculo ajustado de altura de las filas. Valida con nombres largos y un archivo de varias páginas antes de modificar fuente, márgenes o límites de memoria.

### La exportación no muestra los resultados esperados

Comprueba que los resultados de la selección sigan disponibles en la sesión y que se haya utilizado el enlace del grupo correcto. No vuelvas a ejecutar la selección únicamente para generar otro formato.

## Estado y siguientes pasos

### Implementado y validado durante el desarrollo

- Estructura modular y autoload de Composer.
- Acceso administrativo, gestión de sesión y protección de formularios.
- Carga de Excel y selección de hoja.
- Validación de encabezados y procesamiento de registros.
- Almacenamiento temporal y reglas de expiración configuradas.
- Selección aleatoria sin reemplazo.
- Visualización de seleccionados y no seleccionados.
- Exportación a Excel de ambos grupos.
- Vista previa PDF A3 horizontal de ambos grupos.
- Ajuste del cálculo de altura de filas para mejorar la lectura de valores extensos.
- Pruebas funcionales manuales de las exportaciones Excel y PDF.

### Siguientes tareas recomendadas

1. Revisar y completar la cobertura de pruebas automatizadas para los servicios de Excel y PDF.
2. Ejecutar pruebas de integración para entradas inválidas, cantidades límite, sesiones y expiración.
3. Revisar la política de eliminación de archivos y resultados temporales.
4. Validar el despliegue institucional en el servidor Windows previsto, incluyendo HTTPS y permisos.
5. Mantener este README actualizado cuando cambien las reglas, la configuración o la estructura del código.

La aplicación no debe considerarse lista para producción hasta completar la revisión institucional de seguridad, privacidad, configuración y pruebas integrales.

## Licencia y uso

Randomix es un proyecto orientado al uso institucional. La licencia y las condiciones de distribución, modificación y uso deben definirse de acuerdo con las decisiones de la organización responsable.
