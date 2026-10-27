<?php

declare(strict_types=1);

// Define el título y activa el diseño de la zona autenticada.
$pageTitle = 'Unir archivos Excel';
$showAuthenticatedLayout = true;

// Recupera los archivos, las hojas y el resultado almacenados en sesión.
$mergeExcelA = $_SESSION['merge_excel_a'] ?? null;
$mergeExcelB = $_SESSION['merge_excel_b'] ?? null;

$mergeSheetsA = $mergeSheetsA ?? [];
$mergeSheetsB = $mergeSheetsB ?? [];

$mergeResult = $_SESSION['merge_result'] ?? null;

// Recupera el mensaje de la última operación y evita repetirlo.
$mergeFeedback = $_SESSION['merge_feedback'] ?? null;
unset($_SESSION['merge_feedback']);

// Comprueba si ambos archivos están disponibles.
$mergeFilesReady = is_array($mergeExcelA)
    && is_array($mergeExcelB);

// Verifica que el resultado tenga la estructura esperada.
$mergeResultReady = is_array($mergeResult)
    && isset(
    $mergeResult['headers'],
    $mergeResult['records'],
    $mergeResult['total_a'],
    $mergeResult['total_b'],
    $mergeResult['duplicates_removed'],
    $mergeResult['total_merged']
)
    && is_array($mergeResult['headers'])
    && is_array($mergeResult['records']);

// Construye el contenido antes de cargar el layout compartido.
ob_start();
?>

<!-- Facilita el regreso al panel principal. -->

<div class="page-actions">
    <a class="button button-primary" href="index.php?page=dashboard">
        ← Volver al dashboard
    </a>
</div>
<br>

<!-- Explica las etapas del proceso de unión. -->
<section class="dashboard-section" aria-labelledby="merge-workflow-title">
    <div class="dashboard-section__heading">
        <h2 id="merge-workflow-title">¿Cómo funciona la unión?</h2>
        <p>
            Combina dos archivos Excel, conservando una sola fila por
            documento de identidad y dando prioridad al archivo A.
        </p>
    </div>

    <div class="workflow-grid">
        <article class="workflow-card">
            <span class="workflow-card__number">01</span>
            <h3>Cargar los archivos</h3>
            <p>
                Selecciona los dos archivos Excel que deseas combinar.
                Los originales no se modificarán.
            </p>
        </article>

        <article class="workflow-card">
            <span class="workflow-card__number">02</span>
            <h3>Seleccionar las hojas</h3>
            <p>
                Elige la hoja de cada archivo que contiene los estudiantes.
                Los encabezados deben coincidir con el esquema oficial.
            </p>
        </article>

        <article class="workflow-card">
            <span class="workflow-card__number">03</span>
            <h3>Eliminar duplicados</h3>
            <p>
                Randomix utiliza la columna <code>doc</code> como clave.
                Si un documento aparece en ambos archivos, conserva el del A.
            </p>
        </article>

        <article class="workflow-card">
            <span class="workflow-card__number">04</span>
            <h3>Consultar el resultado</h3>
            <p>
                Revisa las estadísticas de la unión y prepara el archivo
                combinado para su descarga.
            </p>
        </article>
    </div>
</section>

<!-- Permite cargar los dos archivos Excel. -->
<section class="dashboard-section" aria-labelledby="merge-title">
    <div class="dashboard-section__heading">
        <h2 id="merge-title">Unir archivos Excel</h2>
        <p>
            Cada archivo puede contener hasta 20.000 filas de datos.
            Ambos deben utilizar los encabezados oficiales de Randomix,
            en el mismo orden.
        </p>
    </div>

    <!-- Muestra los mensajes de éxito o error de la operación. -->
    <?php if (is_array($mergeFeedback)): ?>
        <div class="upload-feedback upload-feedback--<?= ($mergeFeedback['type'] ?? '') === 'success' ? 'success' : 'error' ?>"
            role="status">
            <?= htmlspecialchars(
                (string) ($mergeFeedback['message'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <form class="upload-form merge-form merge-form--files" method="post" action="index.php?page=merge" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
            (string) ($_SESSION['csrf_token'] ?? ''),
            ENT_QUOTES,
            'UTF-8'
        ) ?>">

        <input type="hidden" name="action" value="upload_merge_files">

        <div class="form-group">
            <label for="merge-file-a">Excel A — Archivo prioritario</label>
            <input type="file" id="merge-file-a" name="merge_excel_a_file" accept=".xlsx,.xls" required>
            <p>
                Cuando un documento aparezca en ambos archivos,
                se conservará primero el registro de este archivo.
            </p>
        </div>

        <div class="form-group">
            <label for="merge-file-b">Excel B — Archivo complementario</label>
            <input type="file" id="merge-file-b" name="merge_excel_b_file" accept=".xlsx,.xls" required>
            <p>
                Se incorporarán los estudiantes cuyo documento no
                haya aparecido previamente en el archivo A.
            </p>
        </div>

        <button type="submit" class="button button-primary">
            Cargar ambos archivos
        </button>
    </form>
</section>

<!-- Permite elegir la hoja que se procesará de cada archivo. -->
<?php if ($mergeFilesReady): ?>
    <section class="dashboard-section" aria-labelledby="merge-sheets-title">
        <div class="dashboard-section__heading">
            <h2 id="merge-sheets-title">Seleccionar hojas</h2>
            <p>
                Selecciona las hojas que contienen los registros que deseas unir.
            </p>
        </div>

        <form class="upload-form merge-form merge-form--sheets" method="post" action="index.php?page=merge">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                (string) ($_SESSION['csrf_token'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">

            <input type="hidden" name="action" value="execute_merge">

            <div class="form-group">
                <label for="merge-sheet-a">Hoja del Excel A</label>
                <select id="merge-sheet-a" name="merge_sheet_a" required>
                    <option value="">Selecciona una hoja</option>

                    <?php foreach ($mergeSheetsA as $sheet): ?>
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
                <label for="merge-sheet-b">Hoja del Excel B</label>
                <select id="merge-sheet-b" name="merge_sheet_b" required>
                    <option value="">Selecciona una hoja</option>

                    <?php foreach ($mergeSheetsB as $sheet): ?>
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

            <button type="submit" class="button button-primary">
                Ejecutar unión
            </button>
        </form>
    </section>
<?php endif; ?>

<!-- Presenta las estadísticas cuando la unión ya produjo un resultado. -->
<?php if ($mergeResultReady): ?>
    <section class="dashboard-section" aria-labelledby="merge-results-title">
        <div class="dashboard-section__heading">
            <h2 id="merge-results-title">Resultado de la unión</h2>
            <p>
                Se conservaron los primeros registros de cada documento,
                procesando el archivo A antes que el archivo B.
            </p>
        </div>

        <div class="workflow-grid">
            <article class="workflow-card">
                <h3>Filas del Excel A</h3>
                <p>
                    <?= number_format(
                        (int) $mergeResult['total_a'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </p>
            </article>

            <article class="workflow-card">
                <h3>Filas del Excel B</h3>
                <p>
                    <?= number_format(
                        (int) $mergeResult['total_b'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </p>
            </article>

            <article class="workflow-card">
                <h3>Duplicados eliminados</h3>
                <p>
                    <?= number_format(
                        (int) $mergeResult['duplicates_removed'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </p>
            </article>

            <article class="workflow-card">
                <h3>Registros finales</h3>
                <p>
                    <?= number_format(
                        (int) $mergeResult['total_merged'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </p>
            </article>
        </div>

        <p>
            Filas vacías omitidas:
            <strong>
                <?= number_format(
                    (int) ($mergeResult['omitted_empty_rows'] ?? 0),
                    0,
                    ',',
                    '.'
                ) ?>
            </strong>
        </p>

        <p>
            Filas sin documento omitidas:
            <strong>
                <?= number_format(
                    (int) ($mergeResult['omitted_without_document'] ?? 0),
                    0,
                    ',',
                    '.'
                ) ?>
            </strong>
        </p>


        <!-- Permite descargar el Excel C generado a partir de la unión. -->
        <form class="upload-form" method="post" action="index.php?page=merge">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                (string) ($_SESSION['csrf_token'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">

            <input type="hidden" name="action" value="export_merge">

            <button type="submit" class="button button-primary">
                Descargar Excel C
            </button>
        </form>

        

    </section>
<?php endif; ?>
<!-- Facilita el regreso al dashboard principal. -->
         <br>
        <div class="page-actions">
            <a class="button button-primary" href="index.php?page=dashboard">
                ← Volver al dashboard
            </a>
        </div>
<?php

// Entrega el contenido al layout compartido de la aplicación.
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layout/app.php';
