<?php

declare(strict_types=1);

// Define el título de la página y activa el layout del área privada.
$pageTitle = 'Comprobar estudiantes ganadores del año pasado';
$showAuthenticatedLayout = true;

// Recupera los archivos, las hojas disponibles y el resultado de la sesión.
$comparisonExcelA = $_SESSION['comparison_excel_a'] ?? null;
$comparisonExcelB = $_SESSION['comparison_excel_b'] ?? null;

$comparisonSheetsA = $comparisonSheetsA ?? [];
$comparisonSheetsB = $comparisonSheetsB ?? [];

$comparisonResult = $_SESSION['comparison_result'] ?? null;

// Recupera el mensaje de la última operación y evita mostrarlo nuevamente.
$comparisonFeedback = $_SESSION['comparison_feedback'] ?? null;
unset($_SESSION['comparison_feedback']);

// Determina si los dos archivos están disponibles para comparar.
$comparisonFilesReady = is_array($comparisonExcelA)
    && is_array($comparisonExcelB);

// Comprueba que exista un resultado con la estructura esperada.
$comparisonResultReady = is_array($comparisonResult)
    && isset(
    $comparisonResult['headers'],
    $comparisonResult['records'],
    $comparisonResult['total_original'],
    $comparisonResult['coincidences_removed'],
    $comparisonResult['remaining']
)
    && is_array($comparisonResult['headers'])
    && is_array($comparisonResult['records']);

// Construye el contenido antes de cargar el layout compartido.
ob_start();
?>

<!-- Facilita el regreso al dashboard principal. -->
<br>
<div class="page-actions">
    <a class="button button-primary" href="index.php?page=dashboard">
        ← Volver al dashboard
    </a>
</div>

<!-- Explica paso a paso cómo realizar la comparación. -->
<section class="dashboard-section" aria-labelledby="comparison-workflow-title">
    <div class="dashboard-section__heading">
        <h2 id="comparison-workflow-title">¿Cómo funciona la comparación?</h2>
        <p>
            Sigue estas cuatro etapas para identificar estudiantes que
            ya aparecen en los registros del año pasado.
        </p>
    </div>

    <div class="workflow-grid">
        <article class="workflow-card">
            <span class="workflow-card__number">01</span>
            <h3>Cargar los archivos</h3>
            <p>
                Selecciona el Excel de estudiantes del año actual y el Excel
                que contiene los estudiantes ganadores del año pasado.
            </p>
        </article>

        <article class="workflow-card">
            <span class="workflow-card__number">02</span>
            <h3>Seleccionar las hojas</h3>
            <p>
                Elige la hoja de cada archivo que contiene los registros.
                El archivo del año pasado debe incluir la columna
                <code>doc</code>.
            </p>
        </article>

        <article class="workflow-card">
            <span class="workflow-card__number">03</span>
            <h3>Ejecutar la comparación</h3>
            <p>
                Randomix compara los documentos y excluye del Excel actual
                los registros que coinciden con los del archivo anterior.
                Los archivos originales no se modifican.
            </p>
        </article>

        <article class="workflow-card">
            <span class="workflow-card__number">04</span>
            <h3>Consultar y descargar</h3>
            <p>
                Revisa los registros originales, las coincidencias excluidas
                y los registros restantes. Después descarga el Excel filtrado.
            </p>
        </article>
    </div>
</section>


<!-- Presenta el proceso de comparación de archivos Excel. -->
<section class="dashboard-section" aria-labelledby="comparison-title">

    <div class="dashboard-section__heading">
        <h2 id="comparison-title">Comparación de archivos Excel</h2>

        <p>
            Carga el Excel de los estudiantes del año actual y el Excel del año
            pasado. Randomix excluirá del primero los registros cuyos documentos
            aparezcan en el segundo. Los archivos originales no se modificarán.
        </p>
    </div>

    <!-- Muestra el resultado de la última operación realizada. -->
    <?php if (is_array($comparisonFeedback)): ?>
        <div class="upload-feedback upload-feedback--<?= ($comparisonFeedback['type'] ?? '') === 'success' ? 'success' : 'error' ?>"
            role="status">
            <?= htmlspecialchars(
                (string) ($comparisonFeedback['message'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <!-- Permite cargar los dos archivos en una única solicitud. -->
    <form class="upload-form comparison-form comparison-form--files" method="post" action="index.php?page=comparison" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
            (string) ($_SESSION['csrf_token'] ?? ''),
            ENT_QUOTES,
            'UTF-8'
        ) ?>">

        <input type="hidden" name="action" value="upload_comparison_files">

        <div class="form-group">
            <label for="comparison-file-a">
                Excel A — Estudiantes del año actual
            </label>

            <input type="file" id="comparison-file-a" name="comparison_excel_a_file" accept=".xlsx,.xls" required>

            <p>Debe contener los encabezados oficiales de Randomix.</p>
        </div>

        <div class="form-group">
            <label for="comparison-file-b">
                Excel B — Estudiantes del año anterior
            </label>

            <input type="file" id="comparison-file-b" name="comparison_excel_b_file" accept=".xlsx,.xls" required>

            <p>
                Debe contener una columna cuyo encabezado sea exactamente
                <code>doc</code>.
            </p>
        </div>

        <button type="submit" class="button button-primary">
            Cargar ambos archivos
        </button>
    </form>
</section>

<!-- Permite seleccionar la hoja que se utilizará de cada archivo cargado. -->
<?php if ($comparisonFilesReady): ?>
    <section class="dashboard-section" aria-labelledby="comparison-sheets-title">
        <div class="dashboard-section__heading">
            <h2 id="comparison-sheets-title">Seleccionar hojas</h2>

            <p>
                Selecciona la hoja de cada archivo que contiene los registros
                que deseas comparar.
            </p>
        </div>

        <form class="upload-form comparison-form comparison-form--sheets" method="post" action="index.php?page=comparison">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                (string) ($_SESSION['csrf_token'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">

            <input type="hidden" name="action" value="execute_comparison">

            <div class="form-group">
                <label for="comparison-sheet-a">
                    Hoja del Excel A
                </label>

                <select id="comparison-sheet-a" name="comparison_sheet_a" required>
                    <option value="">Selecciona una hoja</option>

                    <?php foreach ($comparisonSheetsA as $sheet): ?>
                        <option value="<?= htmlspecialchars(
                            (string) $sheet,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
                            <?= htmlspecialchars(
                                (string) $sheet,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="comparison-sheet-b">
                    Hoja del Excel B
                </label>

                <select id="comparison-sheet-b" name="comparison_sheet_b" required>
                    <option value="">Selecciona una hoja</option>

                    <?php foreach ($comparisonSheetsB as $sheet): ?>
                        <option value="<?= htmlspecialchars(
                            (string) $sheet,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
                            <?= htmlspecialchars(
                                (string) $sheet,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>



            <!-- Solicita los años que identifican esta comparación. -->
            <div class="form-group">
                <label for="comparison-current-year">
                    Año actual
                </label>

                <input type="number" id="comparison-current-year" name="comparison_current_year" min="1900" max="2200"
                    step="1" value="<?= htmlspecialchars(
                        (string) ($_SESSION['comparison_current_year'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>" placeholder="Ejemplo: 2026" required>
            </div>

            <div class="form-group">
                <label for="comparison-previous-year">
                    Año anterior
                </label>

                <input type="number" id="comparison-previous-year" name="comparison_previous_year" min="1900" max="2200"
                    step="1" value="<?= htmlspecialchars(
                        (string) ($_SESSION['comparison_previous_year'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>" placeholder="Ejemplo: 2025" required>
            </div>



            <button type="submit" class="button button-primary">
                Ejecutar comparación
            </button>
        </form>
    </section>
<?php endif; ?>

<!-- Presenta las estadísticas únicamente cuando hay un resultado válido. -->
<?php if ($comparisonResultReady): ?>
    <section class="dashboard-section" aria-labelledby="comparison-results-title">
        <div class="dashboard-section__heading">
            <h2 id="comparison-results-title">Resultado de la comparación</h2>

            <p>
                Estos datos corresponden al Excel A después de excluir
                los documentos encontrados en el Excel B.
            </p>
        </div>

        <div class="workflow-grid">
            <article class="workflow-card">
                <h3>Registros originales</h3>
                <p>
                    <?= number_format(
                        (int) $comparisonResult['total_original'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </p>
            </article>

            <article class="workflow-card">
                <h3>Coincidencias excluidas</h3>
                <p>
                    <?= number_format(
                        (int) $comparisonResult['coincidences_removed'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </p>
            </article>

            <article class="workflow-card">
                <h3>Registros restantes</h3>
                <p>
                    <?= number_format(
                        (int) $comparisonResult['remaining'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </p>
            </article>
        </div>

        <!-- Permite descargar el Excel filtrado sin generar una página intermedia. -->
        <div class="export-actions">
            <article class="export-card export-card--excel">
                <h3>Excel filtrado</h3>

                <p>
                    Descarga los registros restantes del Excel A,
                    conservando sus encabezados.
                </p>

                <form method="post" action="index.php?page=comparison">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                        (string) ($_SESSION['csrf_token'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">

                    <input type="hidden" name="action" value="export_comparison">

                    <button type="submit" class="button button-primary">
                        Descargar Excel filtrado
                    </button>
                </form>
            </article>
        </div>

    </section>
<?php endif; ?>

<br>
<div class="page-actions">
    <a class="button button-primary" href="index.php?page=dashboard">
        ← Volver al dashboard
    </a>
</div>
<?php

// Entrega el contenido de esta página al layout compartido.
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layout/app.php';
