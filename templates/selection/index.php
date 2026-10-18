<?php

declare(strict_types=1);

// Define el título y activa la estructura visual del área privada.
$pageTitle = 'Panel administrativo';
$showAuthenticatedLayout = true;

// Construye el contenido de la página antes de cargar el layout.
ob_start();
?>

<section class="dashboard-page">
    <div class="dashboard-welcome">
        <div>
            <span class="eyebrow">PANEL ADMINISTRATIVO</span>

            <h1>Bienvenido a Randomix</h1>

            <p>
                Administra los procesos de selección aleatoria
                de estudiantes desde un solo lugar.
            </p>
        </div>

        <div class="dashboard-welcome__icon" aria-hidden="true">
            R
        </div>
    </div>

    <section
        class="dashboard-section"
        aria-labelledby="workflow-title"
    >
        <div class="dashboard-section__heading">
            <div>
                <h2 id="workflow-title">Proceso de selección</h2>

                <p>
                    El flujo de trabajo que organizaremos en las
                    siguientes fases del proyecto.
                </p>
            </div>
        </div>

        <div class="workflow-grid">
            <article class="workflow-card">
                <span class="workflow-card__number">01</span>

                <div class="workflow-card__icon" aria-hidden="true">
                    ↑
                </div>

                <h3>Importar estudiantes</h3>

                <p>
                    Cargar un archivo Excel y verificar su estructura
                    antes de iniciar el proceso.
                </p>

                <span class="workflow-card__status">
                    Próximamente
                </span>
            </article>

            <article class="workflow-card">
                <span class="workflow-card__number">02</span>

                <div class="workflow-card__icon" aria-hidden="true">
                    ⇄
                </div>

                <h3>Realizar selección</h3>

                <p>
                    Definir la cantidad de estudiantes y ejecutar
                    una selección aleatoria sin reemplazo.
                </p>

                <span class="workflow-card__status">
                    Próximamente
                </span>
            </article>

            <article class="workflow-card">
                <span class="workflow-card__number">03</span>

                <div class="workflow-card__icon" aria-hidden="true">
                    ↓
                </div>

                <h3>Consultar y exportar</h3>

                <p>
                    Consultar los resultados y preparar los archivos
                    de estudiantes seleccionados y no seleccionados.
                </p>

                <span class="workflow-card__status">
                    Próximamente
                </span>
            </article>
        </div>
    </section>

    <aside class="dashboard-notice">
        <span class="dashboard-notice__icon" aria-hidden="true">i</span>

        <div>
            <h2>Estado del sistema</h2>

            <p>
                El acceso administrativo está disponible. Los módulos
                de importación, selección y exportación se incorporarán
                progresivamente.
            </p>
        </div>
    </aside>
</section>

<?php

// Entrega el contenido de la página al layout compartido.
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layout/app.php';
