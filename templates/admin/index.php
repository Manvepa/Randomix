<?php

declare(strict_types=1);

// Define los valores visuales de la página administrativa.
$pageTitle = 'Administración';
$showAuthenticatedLayout = true;

// Garantiza que las variables tengan valores predeterminados.
$adminSummary = $adminSummary ?? [
    'selections' => 0,
    'comparisons' => 0,
    'merges' => 0,
];

$recentActivity = $recentActivity ?? [];

// Formatea cantidades para mostrarlas de forma legible.
$formatNumber = static fn (int $value): string =>
    number_format($value, 0, ',', '.');

// Construye el contenido antes de cargar el layout compartido.
ob_start();
?>

<section class="dashboard-page">

    <div class="dashboard-welcome">
        <div>
            <span class="eyebrow">ÁREA ADMINISTRATIVA</span>

            <h1>Administración de Randomix</h1>

            <p>
                Consulta las operaciones registradas y la actividad
                general de la aplicación.
            </p>
        </div>

        <img
            class="brand-mark"
            src="assets/images/randomix-logo.png"
            alt=""
            aria-hidden="true"
            style="width: 100px; height: auto;"
        >
    </div>

    <section class="dashboard-section" aria-labelledby="summary-title">
        <div class="dashboard-section__heading">
            <div>
                <h2 id="summary-title">Resumen general</h2>
                <p>Operaciones registradas en la base de datos.</p>
            </div>
        </div>

        <div class="workflow-grid">

            <article class="workflow-card">
                <span class="workflow-card__number">01</span>
                <h3>Selecciones realizadas</h3>

                <p>
                    Total de selecciones registradas.
                </p>

                <strong>
                    <?= $formatNumber($adminSummary['selections']) ?>
                </strong>
            </article>

            <article class="workflow-card">
                <span class="workflow-card__number">02</span>
                <h3>Comparaciones realizadas</h3>

                <p>
                    Total de comparaciones registradas.
                </p>

                <strong>
                    <?= $formatNumber($adminSummary['comparisons']) ?>
                </strong>
            </article>

            <article class="workflow-card">
                <span class="workflow-card__number">03</span>
                <h3>Uniones realizadas</h3>

                <p>
                    Total de uniones registradas.
                </p>

                <strong>
                    <?= $formatNumber($adminSummary['merges']) ?>
                </strong>
            </article>

        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="activity-title">
        <div class="dashboard-section__heading">
            <div>
                <h2 id="activity-title">Actividad reciente</h2>

                <p>
                    Las diez operaciones más recientes registradas.
                </p>
            </div>
        </div>

        <?php if ($recentActivity === []): ?>

            <aside class="dashboard-notice">
                <span class="dashboard-notice__icon" aria-hidden="true">i</span>

                <div>
                    <h2>Sin actividad registrada</h2>
                    <p>
                        Todavía no hay operaciones para mostrar.
                    </p>
                </div>
            </aside>

        <?php else: ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Operación</th>
                            <th>Identificador</th>
                            <th>Fecha</th>
                            <th>Registros procesados</th>
                            <th>Resultado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recentActivity as $activity): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(
                                        (string) $activity['operation_type'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $activity['operation_id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $activity['operation_date'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= $formatNumber(
                                        (int) $activity['records_processed']
                                    ) ?>
                                </td>

                                <td>
                                    <?= $formatNumber(
                                        (int) $activity['result_count']
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

    <div>
        <a class="button button-primary" href="index.php?page=dashboard">
            Volver al inicio
        </a>
    </div>

</section>

<?php

// Entrega el contenido al layout compartido de la aplicación.
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layout/app.php';
