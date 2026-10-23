<?php

declare(strict_types=1);

// Define el título y activa la estructura visual del área privada.
$pageTitle = 'Proceso de selección';
$showAuthenticatedLayout = true;

// Recupera el mensaje de la última operación y evita repetirlo.
$uploadFeedback = $_SESSION['upload_feedback'] ?? null;
unset($_SESSION['upload_feedback']);

// Obtiene los metadatos del archivo temporal registrado.
$uploadedExcel = $_SESSION['uploaded_excel'] ?? null;

// Obtiene el resultado de la validación de la hoja seleccionada.
$sheetValidation = $_SESSION['sheet_validation'] ?? null;

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

<section class="dashboard-page">

    <!-- Explica las etapas del proceso de selección aleatoria. -->
    <section class="dashboard-section" aria-labelledby="selection-workflow-title">
        <div class="dashboard-section__heading">
            <div>
                <h2 id="selection-workflow-title">¿Cómo funciona la selección?</h2>
                <p>
                    Sigue estas tres etapas para cargar los estudiantes,
                    ejecutar el sorteo y obtener los resultados.
                </p>
            </div>
        </div>

        <div class="workflow-grid">
            <article class="workflow-card">
                <span class="workflow-card__number">01</span>
                <h3>Importar estudiantes</h3>
                <p>
                    Carga el archivo Excel y selecciona la hoja que contiene
                    los registros que deseas procesar.
                </p>
                <span class="workflow-card__status">
                    Carga y validación
                </span>
            </article>

            <article class="workflow-card">
                <span class="workflow-card__number">02</span>
                <h3>Validar y realizar la selección</h3>
                <p>
                    Comprueba que los encabezados sean correctos y define
                    la cantidad de beneficiarios y suplentes que necesitas.
                </p>
                <span class="workflow-card__status">
                    Ejecución del sorteo
                </span>
            </article>

            <article class="workflow-card">
                <span class="workflow-card__number">03</span>
                <h3>Consultar y exportar</h3>
                <p>
                    Revisa los resultados y descarga los listados de
                    estudiantes seleccionados y no seleccionados en Excel o PDF.
                </p>
                <span class="workflow-card__status">
                    Resultados y exportación
                </span>
            </article>
        </div>
    </section>

    <!-- Agrupa la carga del archivo y la validación de su hoja. -->
    <section class="upload-panel" aria-labelledby="upload-title">
        <div class="upload-panel__heading">
            <span class="eyebrow">PASO 01 · IMPORTACIÓN</span>
            <h2 id="upload-title">Cargar archivo de estudiantes</h2>
            <p>Selecciona el archivo Excel que contiene los registros para el proceso de selección.</p>
        </div>

        <!-- Muestra el resultado de la última carga. -->
        <?php if (is_array($uploadFeedback)): ?>
            <?php
            $feedbackType = ($uploadFeedback['type'] ?? '') === 'success' ? 'success' : 'error';
            ?>
            <div class="upload-feedback upload-feedback--<?= $feedbackType ?>" role="status" aria-live="polite">
                <?= htmlspecialchars((string) ($uploadFeedback['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Envía el archivo al controlador de carga. -->
        <form class="upload-form" action="index.php?page=dashboard" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_excel">
            <input type="hidden" name="csrf_token"
                value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="upload-form__file">
                <label for="excel_file">Archivo Excel</label>
                <input id="excel_file" name="excel_file" type="file" accept=".xlsx,.xls" required>
                <p>Formatos permitidos: XLSX y XLS. Tamaño máximo: 20 MB.</p>
            </div>

            <button class="button button-primary" type="submit">
                <span>Cargar archivo</span>
                <span aria-hidden="true">↑</span>
            </button>
        </form>

        <!-- Muestra el archivo temporal registrado en la sesión. -->
        <?php if (is_array($uploadedExcel)): ?>
            <div class="upload-current">
                <strong>Archivo temporal registrado</strong>
                <p>
                    <?= htmlspecialchars((string) ($uploadedExcel['original_name'] ?? 'Archivo Excel'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p>
                    Disponible hasta:
                    <?php if (isset($uploadedExcel['expires_at']) && is_int($uploadedExcel['expires_at']) && $uploadedExcel['expires_at'] > time()): ?>
                        <?= date('d/m/Y H:i:s', $uploadedExcel['expires_at']) ?>
                    <?php else: ?>
                        No disponible
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Permite seleccionar la hoja del archivo Excel cargado. -->
        <?php if (is_array($uploadedExcel)): ?>
            <div class="sheet-inspection">
                <div class="upload-panel__heading">
                    <span class="eyebrow">PASO 02 · VALIDACIÓN</span>
                    <h2>Seleccionar hoja de estudiantes</h2>
                    <p>Selecciona la hoja que contiene los registros y comprueba que sus encabezados sean correctos.</p>
                </div>

                <?php if (count($excelSheets) > 0): ?>
                    <form class="upload-form" action="index.php?page=dashboard" method="POST">
                        <input type="hidden" name="action" value="validate_sheet">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="upload-form__file">
                            <label for="sheet_name">Hoja del archivo</label>
                            <select id="sheet_name" name="sheet_name" required>
                                <option value="">Selecciona una hoja</option>
                                <?php foreach ($excelSheets as $sheet): ?>
                                    <option value="<?= htmlspecialchars((string) $sheet, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (is_array($sheetValidation) && ($sheetValidation['sheet_name'] ?? null) === $sheet) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $sheet, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button class="button button-primary" type="submit">
                            <span>Validar encabezados</span>
                            <span aria-hidden="true">✓</span>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="upload-feedback upload-feedback--error" role="status">
                        No fue posible obtener las hojas del archivo.
                        Comprueba que el Excel sea válido o vuelve a cargarlo.
                    </div>
                <?php endif; ?>

                <!-- Presenta el resultado de la validación de la hoja. -->
                <?php if (is_array($sheetValidation)): ?>
                    <?php if (($sheetValidation['success'] ?? false) !== true): ?>
                        <div class="upload-feedback upload-feedback--error" role="alert">
                            <strong>No fue posible validar la hoja.</strong>
                            <?php foreach (($sheetValidation['errors'] ?? []) as $error): ?>
                                <p><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (($sheetValidation['valid'] ?? false) === true): ?>
                        <div class="upload-feedback upload-feedback--success" role="status">
                            <strong>¡Encabezados válidos!</strong>
                            <p>
                                La hoja
                                <strong><?= htmlspecialchars((string) $sheetValidation['sheet_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                cumple con la estructura requerida.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="upload-feedback upload-feedback--error" role="alert">
                            <strong>La hoja no cumple con los encabezados requeridos.</strong>

                            <?php if (!empty($sheetValidation['missing'])): ?>
                                <p><strong>Encabezados faltantes:</strong></p>
                                <ul>
                                    <?php foreach ($sheetValidation['missing'] as $header): ?>
                                        <li><?= htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($sheetValidation['extra'])): ?>
                                <p><strong>Encabezados adicionales:</strong></p>
                                <ul>
                                    <?php foreach ($sheetValidation['extra'] as $header): ?>
                                        <li><?= htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($sheetValidation['duplicates'])): ?>
                                <p><strong>Encabezados duplicados:</strong></p>
                                <ul>
                                    <?php foreach ($sheetValidation['duplicates'] as $header): ?>
                                        <li><?= htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Permite configurar la selección después de validar la hoja. -->
        <?php if (is_array($sheetValidation) && ($sheetValidation['success'] ?? false) === true && ($sheetValidation['valid'] ?? false) === true): ?>
            <section class="dashboard-section" aria-labelledby="selection-form-title">
                <div class="dashboard-section__heading">
                    <div>
                        <h2 id="selection-form-title">Selección aleatoria</h2>
                        <p>Indica cuántos estudiantes deseas seleccionar de la hoja validada.</p>
                    </div>
                </div>

                <form class="upload-form" action="index.php?page=dashboard" method="POST">
                    <input type="hidden" name="action" value="execute_selection">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="sheet_name"
                        value="<?= htmlspecialchars((string) $sheetValidation['sheet_name'], ENT_QUOTES, 'UTF-8') ?>">


                    <div class="upload-form__file">
                        <label for="beneficiary_quantity">Cantidad de beneficiarios</label>
                        <input id="beneficiary_quantity" type="number" name="beneficiary_quantity" min="0" step="1"
                            value="0" required>
                    </div>

                    <div class="upload-form__file">
                        <label for="alternate_quantity">Cantidad de suplentes</label>
                        <input id="alternate_quantity" type="number" name="alternate_quantity" min="0" step="1" value="0"
                            required>
                    </div>

                    <div class="upload-form__file">
                        <label for="total_quantity">Cantidad total del sorteo</label>
                        <input id="total_quantity" type="number" value="0" readonly aria-describedby="total_quantity_help">
                        <small id="total_quantity_help">
                            Suma automática de beneficiarios y suplentes.
                        </small>
                    </div>


                    <button class="button button-primary" type="submit">Ejecutar selección</button>
                </form>
            </section>
        <?php endif; ?>
    </section>

    <!-- Presenta los resultados de la selección o los errores encontrados. -->
    <?php if (is_array($selectionResult)): ?>
        <section class="dashboard-section" aria-labelledby="selection-results-title">
            <div class="dashboard-section__heading">
                <div>
                    <h2 id="selection-results-title">Resultados de la selección</h2>
                    <p>Consulta los estudiantes seleccionados y los que no fueron seleccionados.</p>
                </div>
            </div>

            <?php if (($selectionResult['success'] ?? false) !== true): ?>
                <!-- Informa los errores sin exponer detalles internos. -->
                <div class="alert alert-error" role="alert">
                    <strong>No fue posible ejecutar la selección.</strong>
                    <?php foreach (($selectionResult['errors'] ?? []) as $error): ?>
                        <p><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php
                // Recupera los grupos devueltos por SelectionService.
                $selectedRecords = $selectionResult['selected'] ?? [];
                $notSelectedRecords = $selectionResult['not_selected'] ?? [];

                // Cuenta los estudiantes seleccionados con coincidencias de apellidos.
                $hermanosEncontrados = count(
                    $selectionResult['selected_matching_surname_indexes'] ?? []
                );

                // Obtiene los encabezados originales incluso si no hubo seleccionados.
                $allRecords = array_merge($selectedRecords, $notSelectedRecords);
                $resultHeaders = $allRecords !== [] ? array_keys($allRecords[0]) : [];

                // Convierte los valores de las celdas en texto seguro para HTML.
                $formatResultCell = static function ($value): string {
                    if ($value === null || $value === '') {
                        return '—';
                    }

                    if (is_bool($value)) {
                        return $value ? 'Sí' : 'No';
                    }

                    if (is_scalar($value)) {
                        return (string) $value;
                    }

                    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                };
                ?>

                <!-- Resume la cantidad de registros procesados. -->
                <div class="dashboard-section__heading">
                    <p><strong>Total de estudiantes:</strong> <?= (int) $selectionResult['total_records'] ?></p>
                    <p><strong>Seleccionados:</strong> <?= (int) $selectionResult['selected_count'] ?></p>
                    <p><strong>No seleccionados:</strong> <?= (int) $selectionResult['not_selected_count'] ?></p>
                    <p><strong>Hermanos identificados:</strong> <?= (int) $hermanosEncontrados ?></p>
                    </p>
                </div>


                <div class="export-actions" aria-label="Exportar y visualizar resultados">

                    <!-- Estudiantes seleccionados en Excel -->
                    <a class="export-card export-card--excel" href="index.php?page=dashboard&amp;export=selected">
                        <span class="export-card__icon" aria-hidden="true">XLSX</span>
                        <span class="export-card__content">
                            <span class="export-card__eyebrow">Microsoft Excel</span>
                            <span class="export-card__title">Estudiantes seleccionados</span>
                            <span class="export-card__description">
                                Descarga los registros elegidos en una hoja de cálculo.
                            </span>
                            <span class="export-card__action">
                                Descargar Excel <span aria-hidden="true">↓</span>
                            </span>
                        </span>
                        <span class="export-card__arrow" aria-hidden="true">›</span>
                    </a>

                    <!-- Estudiantes seleccionados en PDF -->
                    <a class="export-card export-card--pdf" href="index.php?page=dashboard&amp;pdf=selected" target="_blank"
                        rel="noopener noreferrer">
                        <span class="export-card__icon" aria-hidden="true">PDF</span>
                        <span class="export-card__content">
                            <span class="export-card__eyebrow">Documento PDF</span>
                            <span class="export-card__title">Estudiantes seleccionados</span>
                            <span class="export-card__description">
                                Abre la vista previa del documento con los estudiantes elegidos.
                            </span>
                            <span class="export-card__action">
                                Visualizar PDF <span aria-hidden="true">↗</span>
                            </span>
                        </span>
                        <span class="export-card__arrow" aria-hidden="true">›</span>
                    </a>

                    <!-- Estudiantes no seleccionados en Excel -->
                    <a class="export-card export-card--excel" href="index.php?page=dashboard&amp;export=not_selected">
                        <span class="export-card__icon" aria-hidden="true">XLSX</span>
                        <span class="export-card__content">
                            <span class="export-card__eyebrow">Microsoft Excel</span>
                            <span class="export-card__title">Estudiantes no seleccionados</span>
                            <span class="export-card__description">
                                Descarga los registros que no fueron elegidos.
                            </span>
                            <span class="export-card__action">
                                Descargar Excel <span aria-hidden="true">↓</span>
                            </span>
                        </span>
                        <span class="export-card__arrow" aria-hidden="true">›</span>
                    </a>

                    <!-- Estudiantes no seleccionados en PDF -->
                    <a class="export-card export-card--pdf" href="index.php?page=dashboard&amp;pdf=not_selected" target="_blank"
                        rel="noopener noreferrer">
                        <span class="export-card__icon" aria-hidden="true">PDF</span>
                        <span class="export-card__content">
                            <span class="export-card__eyebrow">Documento PDF</span>
                            <span class="export-card__title">Estudiantes no seleccionados</span>
                            <span class="export-card__description">
                                Abre la vista previa de los estudiantes restantes.
                            </span>
                            <span class="export-card__action">
                                Visualizar PDF <span aria-hidden="true">↗</span>
                            </span>
                        </span>
                        <span class="export-card__arrow" aria-hidden="true">›</span>
                    </a>

                    <!-- Suplentes en Excel -->
                    <a class="export-card export-card--excel" href="index.php?page=dashboard&amp;export=alternates">
                        <span class="export-card__icon" aria-hidden="true">XLSX</span>
                        <span class="export-card__content">
                            <span class="export-card__eyebrow">Microsoft Excel</span>
                            <span class="export-card__title">Suplentes seleccionados</span>
                            <span class="export-card__description">
                                Descarga únicamente los suplentes seleccionados.
                            </span>
                            <span class="export-card__action">
                                Descargar Excel <span aria-hidden="true">↓</span>
                            </span>
                        </span>
                        <span class="export-card__arrow" aria-hidden="true">›</span>
                    </a>

                    <!-- Suplentes en PDF -->
                    <a class="export-card export-card--pdf" href="index.php?page=dashboard&amp;pdf=alternates" target="_blank"
                        rel="noopener noreferrer">
                        <span class="export-card__icon" aria-hidden="true">PDF</span>
                        <span class="export-card__content">
                            <span class="export-card__eyebrow">Documento PDF</span>
                            <span class="export-card__title">Suplentes seleccionados</span>
                            <span class="export-card__description">
                                Consulta únicamente los suplentes seleccionados.
                            </span>
                            <span class="export-card__action">
                                Visualizar PDF <span aria-hidden="true">↗</span>
                            </span>
                        </span>
                        <span class="export-card__arrow" aria-hidden="true">›</span>
                    </a>

                </div>

            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Informa sobre el alcance funcional actual. -->
    <aside class="dashboard-notice">
        <span class="dashboard-notice__icon" aria-hidden="true">i</span>
        <div>
            <h2>Estado del sistema</h2>
            <p>La autenticación, la carga y validación del Excel, la selección aleatoria y la exportación de resultados
                a Excel y PDF están disponibles.</p>
        </div>
    </aside>

    <!-- Facilita el regreso al dashboard principal. -->
     <br>
    <div class="page-actions">
        <a class="button button-primary" href="index.php?page=dashboard">
            ← Volver al dashboard
        </a>
    </div>
</section>

<?php

// Entrega el contenido de la página al layout compartido.
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layout/app.php';
