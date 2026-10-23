<?php

declare(strict_types=1);

// Define los valores visuales de la página administrativa.
$pageTitle = 'Panel administrativo';
$showAuthenticatedLayout = true;

// Construye el contenido de la página antes de cargar el layout.
ob_start();
?>

<section class="dashboard-page">

    <!-- Presentación principal del panel administrativo. -->
    <div class="dashboard-welcome">
        <div>
            <span class="eyebrow">PANEL ADMINISTRATIVO</span>
            <h1>Bienvenido a Randomix</h1>
            <p>
                Administra los procesos de selección aleatoria
                y verificación de estudiantes desde un solo lugar.
            </p>
        </div>

        <img class="brand-mark" src="assets/images/randomix-logo.png" alt="" aria-hidden="true"
            style="width: 100px; height: auto;">
    </div>
    <!-- Agrupa los dos procesos en una cuadrícula adaptable. -->
    <div class="dashboard-processes">
        <!-- Acceso al proceso principal de selección. -->
        <section class="dashboard-section" aria-labelledby="selection-title">
            <div class="dashboard-section__heading">
                <div>
                    <h2 id="selection-title">Proceso de selección</h2>
                    <p>
                        Carga y valida los estudiantes, ejecuta el sorteo
                        y consulta los resultados.
                    </p>
                </div>
            </div>

            <div class="workflow-grid">
                <article class="workflow-card">
                    <span class="workflow-card__number">01</span>

                    <div class="workflow-card__icon" aria-hidden="true">
                        &#8593;
                    </div>

                    <h3>Realizar prueba de aleatoriedad</h3>

                    <p>
                        Accede al proceso de carga de estudiantes,
                        validación del archivo y ejecución de la selección.
                    </p>

                    <a class="button button-primary" href="index.php?page=selection">
                        Ir al proceso de selección
                    </a>
                </article>
            </div>
        </section>

        <!-- Acceso a la comprobación de ganadores de años anteriores. -->
        <section class="dashboard-section" aria-labelledby="comparison-title">
            <div class="dashboard-section__heading">
                <div>
                    <h2 id="comparison-title">
                        Verificación de estudiantes ganadores
                    </h2>

                    <p>
                        Comprueba si los estudiantes seleccionados el año pasado
                        aparecen en el archivo actual.
                    </p>
                </div>
            </div>

            <div class="workflow-grid">
                <article class="workflow-card">
                    <span class="workflow-card__number">02</span>

                    <div class="workflow-card__icon" aria-hidden="true">
                        &#8644;
                    </div>

                    <h3>Comprobar estudiantes ganadores del año pasado</h3>

                    <p>
                        Compara los archivos Excel para identificar coincidencias
                        mediante el documento de identidad.
                    </p>

                    <a class="button button-primary" href="index.php?page=comparison">
                        Ir a la verificación
                    </a>
                </article>
            </div>
        </section>
    </div>
    <br>


    <!-- Acceso a la unión de dos archivos Excel. -->
    <section class="dashboard-section" aria-labelledby="merge-title">
        <div class="dashboard-section__heading">
            <div>
                <h2 id="merge-title">Unión de archivos Excel</h2>

                <p>
                    Combina dos archivos de estudiantes y elimina
                    registros duplicados mediante el documento de identidad.
                </p>
            </div>
        </div>

        <div class="workflow-grid">
            <article class="workflow-card">
                <span class="workflow-card__number">03</span>

                <div class="workflow-card__icon" aria-hidden="true">
                    &#8645;
                </div>

                <h3>Unir Excel</h3>

                <p>
                    Carga dos archivos Excel, conserva la prioridad
                    del archivo A y genera un archivo combinado sin
                    estudiantes duplicados.
                </p>

                <a class="button button-primary" href="index.php?page=merge">
                    Ir a unir Excel
                </a>
            </article>
        </div>
    </section>

    <!-- Informa sobre las funciones disponibles en el sistema. -->
    <aside class="dashboard-notice">
        <span class="dashboard-notice__icon" aria-hidden="true">i</span>

        <div>
            <h2>Estado del sistema</h2>
            <p>
                La autenticación, la carga y validación del Excel,
                la selección aleatoria y la exportación de resultados
                a Excel y PDF están disponibles.
            </p>
        </div>
    </aside>

</section>

<?php

// Entrega el contenido al layout compartido de la aplicación.
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layout/app.php';
