# Randomix

## Habilitar las extensiones `gd` y `zip` de PHP

Randomix necesita que las extensiones `gd` y `zip` estén habilitadas para que ciertas funciones y dependencias de PHP funcionen correctamente.

1. Abre el **Panel de control de XAMPP**.
2. En la fila **Apache**, haz clic en **Config**.
3. Selecciona **PHP (php.ini)**. Se abrirá el archivo de configuración de PHP en un editor de texto.
4. Busca la línea que contiene `;extension=gd`. En algunas versiones puede aparecer como `;extension=gd` o `;extension=gd2`.
5. Elimina únicamente el punto y coma (`;`) del comienzo para que quede así:

   ```ini
   extension=gd
   ```

6. Busca la línea `;extension=zip` y elimina también el punto y coma del comienzo para que quede así:

   ```ini
   extension=zip
   ```

7. Guarda el archivo `php.ini` y cierra el editor.
8. Vuelve al Panel de control de XAMPP y reinicia Apache: haz clic en **Stop** y después en **Start**.

**Importante:** si no encuentras alguna de esas líneas, no agregues varias versiones de la misma extensión. Revisa primero el archivo `php.ini` que abre XAMPP desde **Apache → Config → PHP (php.ini)**. En versiones actuales de PHP, normalmente se usan `extension=gd` y `extension=zip`.

### Comprobar que las extensiones quedaron habilitadas

Abre una terminal de Windows (CMD) y ejecuta:

```bat
C:\xampp\php\php.exe -m | findstr /I "^(gd|zip)$"
```

Si ambas extensiones están habilitadas en ese PHP, deberían aparecer las líneas `gd` y `zip`.

Si el comando no muestra una extensión, comprueba que editaste el `php.ini` correcto y que la ruta de PHP coincide con tu instalación. Para ver qué archivo de configuración usa PHP por consola, ejecuta:

```bat
C:\xampp\php\php.exe --ini
```



Randomix es una aplicación web institucional que permite trabajar con
listados de estudiantes en Excel, realizar procesos de selección
aleatoria, comparar archivos, unir archivos Excel y consultar o
descargar resultados.

Esta guía está escrita para personas que no tienen experiencia en
programación. Sigue los pasos en orden. No necesitas Docker: el entorno
de desarrollo previsto utiliza Windows y XAMPP.

> **Importante:** esta guía explica cómo preparar el entorno local. Los
> valores concretos de la base de datos, la creación del usuario
> administrador y cualquier migración deben verificarse en los archivos
> actuales del proyecto. No inventes credenciales ni ejecutes scripts de
> base de datos sin revisar su contenido.

## 1. Antes de comenzar: qué necesitas

Instala o confirma que tienes estos programas:

1.  **Windows**: sistema operativo del equipo.
2.  **XAMPP**: proporciona Apache, PHP y MariaDB/MySQL para ejecutar la
    aplicación localmente.
3.  **Composer**: instala las bibliotecas PHP que necesita Randomix.
4.  **Git**: permite descargar el código desde GitHub y actualizarlo.

No necesitas instalar Docker.

### ¿Qué es XAMPP?

XAMPP es un conjunto de herramientas que permite ejecutar una aplicación
web en tu propio computador. En esta guía se supone que XAMPP está
instalado en:

``` text
C:\xampp
```

Y que los proyectos web se guardan en:

``` text
C:\xampp\htdocs
```

Si XAMPP está instalado en otra ubicación, tendrás que adaptar las rutas
de los comandos.

### Verificar que los programas estén instalados

Abre **PowerShell**:

1.  Pulsa la tecla Windows.
2.  Escribe `PowerShell`.
3.  Abre Windows PowerShell o Terminal.
4.  Copia y ejecuta cada comando por separado.

Comprueba PHP de XAMPP:

``` powershell
C:\xampp\php\php.exe -v
```

Comprueba Composer:

``` powershell
composer --version
```

Comprueba Git:

``` powershell
git --version
```

Si un comando indica que no se reconoce o que el archivo no existe,
instala el programa que falta o revisa su ubicación antes de continuar.

## 2. Descargar Randomix

### Opción A: descargar el código con Git

Esta es la opción recomendada si ya tienes Git instalado.

En PowerShell, ejecuta:

``` powershell
Set-Location "C:\xampp\htdocs"
git clone https://github.com/Manvepa/Randomix.git
Set-Location "C:\xampp\htdocs\Randomix"
```

El primer comando entra en la carpeta donde XAMPP guarda los proyectos.
El segundo descarga Randomix desde GitHub. El tercero entra en la
carpeta del proyecto.

Si Windows indica que la carpeta `Randomix` ya existe, **no vuelvas a
clonar el repositorio encima**. Entra en la carpeta existente:

``` powershell
Set-Location "C:\xampp\htdocs\Randomix"
```

### Opción B: ya tienes el proyecto descargado

Si la carpeta ya existe, abre PowerShell y ejecuta:

``` powershell
Set-Location "C:\xampp\htdocs\Randomix"
```

Todos los comandos siguientes se deben ejecutar desde la carpeta
`C:\xampp\htdocs\Randomix`, salvo que se indique otra ruta.

## 3. Iniciar los servicios de XAMPP

1.  Abre **XAMPP Control Panel** desde el menú Inicio de Windows.
2.  Busca la fila **Apache** y pulsa **Start**.
3.  Busca la fila **MySQL** y pulsa **Start** si Randomix utiliza la
    base de datos configurada en tu instalación.
4.  Espera a que los servicios aparezcan como iniciados.

Para comprobar Apache, abre el navegador y visita:

``` text
http://localhost/
```

Si se muestra la página inicial de XAMPP, Apache está respondiendo.

Si Apache o MySQL no inicia, no continúes todavía. Revisa el mensaje que
aparece en XAMPP Control Panel; puede existir un conflicto de puertos o
una configuración pendiente.

## 4. Instalar las dependencias de Randomix

Las dependencias son bibliotecas de terceros que Randomix utiliza para
leer Excel, generar archivos y ejecutar otras funciones. Composer las
instala por ti.

En PowerShell, confirma que estás en la carpeta del proyecto:

``` powershell
Set-Location "C:\xampp\htdocs\Randomix"
```

Después ejecuta:

``` powershell
composer install
```

Espera a que termine. Este comando lee `composer.json` y `composer.lock`
e instala las versiones previstas por el proyecto. También genera el
autoloader que PHP utiliza para encontrar las clases.

Cuando termine, comprueba que existe este archivo:

``` text
C:\xampp\htdocs\Randomix\vendor\autoload.php
```

Si `composer install` termina con un error relacionado con la versión de
PHP, revisa la versión que utiliza Composer:

``` powershell
php -v
```

Compárala con la versión de PHP de XAMPP:

``` powershell
C:\xampp\php\php.exe -v
```

Si son diferentes, Composer podría estar utilizando otra instalación de
PHP. Corrige esa configuración antes de continuar; no cambies versiones
de dependencias al azar.

## 5. Preparar la configuración local

Randomix puede necesitar parámetros de configuración para conectarse a
la base de datos y establecer opciones del entorno.

1.  En la carpeta del proyecto, busca el archivo `.env.example`.
2.  Revisa también los archivos de configuración, especialmente
    `config/` y cualquier documentación dentro de `database/`.
3.  Confirma qué variables lee realmente el código antes de crear o
    modificar archivos de configuración.
4.  Si el proyecto usa `.env`, crea una copia local de `.env.example`
    con el nombre `.env` y completa únicamente las variables que el
    proyecto requiere.

Puedes abrir los archivos con Visual Studio Code, Bloc de notas u otro
editor de texto.

**No copies valores de ejemplo sin comprobarlos.** El nombre de la base
de datos, el usuario, la contraseña y la URL dependen de la
configuración de tu instalación.

No publiques el archivo `.env` ni compartas contraseñas. Debe permanecer
fuera del control de versiones.

## 6. Preparar la base de datos

Randomix utiliza MariaDB/MySQL para guardar información administrativa y
el historial de operaciones cuando así lo establece la configuración
activa.

### 6.1 Iniciar MySQL en XAMPP

En XAMPP Control Panel, confirma que **MySQL** esté iniciado.

### 6.2 Identificar el esquema correcto

Antes de crear una base de datos o tablas:

1.  Revisa la configuración de conexión de Randomix.
2.  Busca en el proyecto los archivos SQL de migración o inicialización.
3.  Lee el contenido de los archivos antes de ejecutarlos.
4.  Confirma si el proyecto requiere crear la base de datos, ejecutar
    una migración y crear un usuario administrador.

No ejecutes un SQL antiguo ni elimines tablas existentes para intentar
solucionar un error. Una migración puede modificar o borrar información.

### 6.3 Crear o inicializar la base de datos

Utiliza el procedimiento y el esquema que correspondan a la versión
actual del proyecto. Configura los mismos datos de conexión en los
archivos de configuración de Randomix.

**Esta guía no proporciona un nombre de base de datos, contraseña de
administrador ni comando de migración universal**, porque esos valores
deben coincidir con los archivos reales del proyecto. Si no encuentras
el procedimiento de inicialización, solicita al responsable técnico que
confirme los pasos antes de continuar.

## 7. Crear o confirmar el acceso administrativo

Para ingresar a Randomix necesitas una cuenta administrativa válida para
la instalación.

-   Si la base de datos ya contiene una cuenta válida, utiliza las
    credenciales entregadas por el responsable del sistema.
-   Si existe un script o comando para crear el administrador, revisa
    sus instrucciones y ejecútalo únicamente según el procedimiento
    oficial del proyecto.
-   No supongas una contraseña por defecto ni compartas credenciales en
    el repositorio.

Si no existe una cuenta y no encuentras instrucciones para crearla,
detente y consulta el procedimiento de inicialización vigente. No
insertes contraseñas directamente en la base de datos.

## 8. Abrir Randomix en el navegador

Con las dependencias instaladas, la configuración preparada y los
servicios necesarios iniciados:

1.  Abre el navegador.
2.  Visita la dirección local:

``` text
http://localhost/Randomix/public/
```

3.  Inicia sesión con una cuenta administrativa válida.

Si la aplicación utiliza una configuración de Apache diferente, la
dirección puede cambiar. El punto de entrada web del proyecto es
`public/index.php`.

Si aparece una página en blanco o un error, consulta la sección
[Solución de problemas](#solución-de-problemas).

## 9. Cómo utilizar Randomix

### Proceso de selección

1.  Entra a la sección **Proceso de selección**.
2.  Carga el archivo Excel con extensión `.xls` o `.xlsx`.
3.  Selecciona la hoja que contiene el listado de estudiantes.
4.  Ejecuta la validación.
5.  Comprueba que los encabezados sean correctos.
6.  Indica cuántos estudiantes deseas seleccionar.
7.  Ejecuta la selección aleatoria.
8.  Revisa los registros seleccionados y no seleccionados.
9.  Descarga los resultados en Excel o abre las vistas previas PDF
    disponibles.

La selección es aleatoria y sin reemplazo: un registro no puede quedar
simultáneamente en ambos grupos en la misma ejecución.

### Verificación de estudiantes ganadores

1.  Entra a la sección de comparación o verificación.
2.  Carga los archivos solicitados por la pantalla.
3.  Selecciona la hoja correspondiente en cada archivo.
4.  Sigue las instrucciones de la interfaz para ejecutar la comparación.
5.  Revisa y descarga el resultado disponible.

Las reglas de comparación son las definidas por la versión instalada.

### Unión de archivos Excel

1.  Entra a **Unión de archivos Excel**.
2.  Carga los dos archivos solicitados.
3.  Selecciona la hoja correspondiente en cada uno.
4.  Ejecuta la unión.
5.  Revisa el resultado y descarga el archivo generado.

No modifiques los encabezados para hacer que un archivo pase la
validación sin confirmar primero las reglas del proyecto.

### Panel administrativo

El panel administrativo presenta estadísticas e historial de
operaciones. La actividad reciente muestra hasta 10 operaciones y el
historial completo permite consultar los registros por páginas.

Los enlaces de descarga dependen de que el archivo generado continúe
disponible y de que la cuenta tenga autorización para acceder a él.

## 10. Reglas de los archivos Excel

La configuración documentada del proyecto contempla:

  Regla                              Comportamiento
  ---------------------------------- --------------------------------------------
  Formatos admitidos                 `.xls` y `.xlsx`
  Tamaño máximo documentado          20 MB por archivo
  Máximo de registros documentado    20.000 registros no vacíos
  Filas completamente vacías         Se ignoran
  Filas parcialmente diligenciadas   Se conservan
  Filas duplicadas                   Se conservan como registros independientes
  Archivos temporales                Retención configurada de 15 minutos
  Selección                          Aleatoria y sin reemplazo

Los límites efectivos dependen de la configuración de la versión
instalada. No proceses datos reales hasta confirmar que el archivo y sus
encabezados cumplen las reglas de la aplicación.

### Encabezados requeridos para el proceso de selección

El listado de selección documentado utiliza estos 20 encabezados:

1.  `ESTADO_ALUMNO`
2.  `DANE`
3.  `INSTITUCION`
4.  `SEDE`
5.  `GRADO`
6.  `JORNADA`
7.  `tipo_doc`
8.  `doc`
9.  `apellido_1`
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

Respeta los nombres de los encabezados. Antes de usar un Excel
institucional, confirma que coincidan con los validadores de la versión
instalada.

## 11. Archivos de resultados

### Excel

La aplicación puede generar archivos `.xlsx` separados para los grupos
de seleccionados y no seleccionados. Los resultados conservan el orden
de los encabezados originales.

### PDF

La vista previa PDF disponible utiliza formato A3 horizontal. Se abre en
el navegador y está pensada para consultar o imprimir los resultados. No
asumas que el PDF se guarda como archivo permanente: el comportamiento
depende del servicio de exportación configurado.

Los resultados y archivos temporales pueden expirar. Descarga y guarda
los documentos que debas conservar en la ubicación institucional
autorizada.

## 12. Solución de problemas frecuentes

### XAMPP no inicia Apache

-   Confirma que estás usando la instalación correcta de XAMPP.
-   Lee el mensaje de error del panel.
-   Comprueba si otro programa está utilizando el puerto requerido.
-   No cambies puertos sin revisar la configuración de Apache.

### Randomix no abre

-   Comprueba que Apache esté iniciado.
-   Confirma que estás utilizando la URL correcta.
-   Verifica que exista `public/index.php`.
-   Comprueba que Composer haya instalado las dependencias y que exista
    `vendor/autoload.php`.
-   Revisa los registros de error de Apache y PHP.

### Error al conectar con la base de datos

-   Comprueba que MySQL esté iniciado.
-   Verifica el nombre de la base de datos, el usuario, la contraseña y
    el puerto configurados.
-   Confirma que la base de datos y las tablas requeridas hayan sido
    inicializadas.
-   No asumas que el usuario `root` tiene una contraseña vacía.

### Composer muestra errores

-   Ejecuta los comandos desde la carpeta del proyecto.
-   Comprueba la versión de PHP que utilizan Composer y XAMPP.
-   Lee el mensaje completo antes de cambiar dependencias.
-   No borres `composer.lock` para intentar resolver el problema.

### El archivo Excel no se puede cargar

-   Comprueba que tenga extensión `.xls` o `.xlsx`.
-   Verifica el tamaño máximo permitido.
-   Asegúrate de seleccionar la hoja correcta.
-   Comprueba que los encabezados sean los requeridos.
-   Vuelve a cargar el archivo si el temporal ha vencido.

### No puedo descargar un resultado

-   Confirma que has iniciado sesión con una cuenta autorizada.
-   Comprueba que la operación haya generado el archivo.
-   Si el archivo temporal expiró, puede ser necesario repetir el
    proceso según las reglas de la aplicación.

### La página aparece en blanco

-   No repitas operaciones con datos reales sin saber si la primera
    solicitud se ejecutó.
-   Revisa los registros de error de PHP y Apache.
-   Si eres responsable del código, valida la sintaxis del archivo PHP
    modificado con el ejecutable de XAMPP. Por ejemplo:

``` powershell
C:\xampp\php\php.exe -l .\src\Controllers\PageController.php
```

## 13. Seguridad y protección de datos

Los archivos pueden contener información personal de estudiantes. Antes
de utilizar datos reales:

-   No subas listados de estudiantes ni archivos exportados al
    repositorio.
-   No publiques el archivo `.env`, contraseñas ni credenciales.
-   Mantén los archivos temporales fuera del directorio público.
-   Utiliza cuentas con los permisos mínimos necesarios.
-   No compartas credenciales administrativas.
-   Utiliza únicamente archivos institucionales autorizados.
-   Confirma que los archivos temporales se eliminen según la política
    definida.
-   Para producción, configura HTTPS, permisos adecuados y depuración
    desactivada.
-   Cumple las políticas institucionales de protección de datos
    personales.

El almacenamiento temporal no garantiza por sí solo que no existan
copias en otros lugares. El despliegue debe respetar las políticas de
tratamiento de datos de la organización.

## 14. Información técnica para mantenimiento

El proyecto utiliza PHP, Composer, XAMPP, MariaDB/MySQL cuando está
configurado, PhpSpreadsheet y componentes de exportación PDF.

La estructura principal incluye:

-   `public/`: punto de entrada web y recursos públicos.
-   `src/Controllers/`: coordinación de solicitudes.
-   `src/Import/`: lectura y procesamiento de Excel.
-   `src/Services/`: lógica de negocio, historial y exportaciones.
-   `src/Validators/`: validaciones.
-   `templates/`: vistas de la aplicación.
-   `storage/temporary/`: archivos temporales.
-   `tests/`: pruebas y scripts de validación.
-   `vendor/`: dependencias instaladas por Composer.

Los nombres y directorios exactos pueden evolucionar. El repositorio y
el código actual son la referencia definitiva.

## 15. Estado del proyecto

Entre las funciones desarrolladas se encuentran la importación y
validación de Excel, selección aleatoria, comparación, unión de
archivos, exportaciones, panel administrativo e historial de
operaciones.

Antes de considerar la aplicación lista para producción, deben
completarse las pruebas integrales y las revisiones institucionales de
seguridad, privacidad, configuración y despliegue.

## Licencia y uso

Randomix es un proyecto orientado al uso institucional. La licencia y
las condiciones de distribución, modificación y uso deben definirse de
acuerdo con las decisiones de la organización responsable.
