<?php

declare(strict_types=1);

// Define el título y activa la estructura visual del área privada.
$pageTitle = 'Panel administrativo';
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

<section class="dashboard-page">

    <!-- Bienvenida al panel administrativo. -->
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

    <!-- Agrupa la carga del archivo y la validación de su hoja. -->
    <section
        class="upload-panel"
        aria-labelledby="upload-title"
    >
        <div class="upload-panel__heading">
            <span class="eyebrow">PASO 01 · IMPORTACIÓN</span>

            <h2 id="upload-title">Cargar archivo de estudiantes</h2>

            <p>
                Selecciona el archivo Excel que contiene los registros
                para el proceso de selección.
            </p>
        </div>

        <!-- Muestra el resultado de la última carga. -->
        <?php if (is_array($uploadFeedback)): ?>
            <?php
            $feedbackType = ($uploadFeedback['type'] ?? '') === 'success'
                ? 'success'
                : 'error';
            ?>

            <div
                class="upload-feedback upload-feedback--<?= $feedbackType ?>"
                role="status"
                aria-live="polite"
            >
                <?= htmlspecialchars(
                    (string) ($uploadFeedback['message'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <!-- Envía el archivo al controlador de carga. -->
        <form
            class="upload-form"
            action="index.php?page=dashboard"
            method="POST"
            enctype="multipart/form-data"
        >
            <input
                type="hidden"
                name="action"
                value="upload_excel"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    (string) $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="upload-form__file">
                <label for="excel_file">
                    Archivo Excel
                </label>

                <input
                    id="excel_file"
                    name="excel_file"
                    type="file"
                    accept=".xlsx,.xls"
                    required
                >

                <p>
                    Formatos permitidos: XLSX y XLS.
                    Tamaño máximo: 20 MB.
                </p>
            </div>

            <button
                class="button button-primary"
                type="submit"
            >
                <span>Cargar archivo</span>
                <span aria-hidden="true">↑</span>
            </button>
        </form>

        <!-- Muestra el archivo temporal registrado en la sesión. -->
        <?php if (is_array($uploadedExcel)): ?>
            <div class="upload-current">
                <strong>Archivo temporal registrado</strong>

                <p>
                    <?= htmlspecialchars(
                        (string) (
                            $uploadedExcel['original_name']
                            ?? 'Archivo Excel'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <p>
                    Disponible hasta:

                    <?php if (
                        isset($uploadedExcel['expires_at'])
                        && is_int($uploadedExcel['expires_at'])
                        && $uploadedExcel['expires_at'] > time()
                    ): ?>
                        <?= date(
                            'd/m/Y H:i:s',
                            $uploadedExcel['expires_at']
                        ) ?>
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

                    <p>
                        Selecciona la hoja que contiene los registros
                        y comprueba que sus encabezados sean correctos.
                    </p>
                </div>

                <?php if (count($excelSheets) > 0): ?>
                    <form
                        class="upload-form"
                        action="index.php?page=dashboard"
                        method="POST"
                    >
                        <input
                            type="hidden"
                            name="action"
                            value="validate_sheet"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                (string) $_SESSION['csrf_token'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <div class="upload-form__file">
                            <label for="sheet_name">
                                Hoja del archivo
                            </label>

                            <select
                                id="sheet_name"
                                name="sheet_name"
                                required
                            >
                                <option value="">
                                    Selecciona una hoja
                                </option>

                                <?php foreach ($excelSheets as $sheet): ?>
                                    <option
                                        value="<?= htmlspecialchars(
                                            (string) $sheet,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        <?= (
                                            is_array($sheetValidation)
                                            && ($sheetValidation['sheet_name'] ?? null) === $sheet
                                        ) ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            (string) $sheet,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button
                            class="button button-primary"
                            type="submit"
                        >
                            <span>Validar encabezados</span>
                            <span aria-hidden="true">✓</span>
                        </button>
                    </form>
                <?php else: ?>
                    <div
                        class="upload-feedback upload-feedback--error"
                        role="status"
                    >
                        No fue posible obtener las hojas del archivo.
                        Comprueba que el Excel sea válido o vuelve a cargarlo.
                    </div>
                <?php endif; ?>

                <!-- Presenta el resultado de la validación de la hoja. -->
                <?php if (is_array($sheetValidation)): ?>

                    <?php if (($sheetValidation['success'] ?? false) !== true): ?>
                        <div
                            class="upload-feedback upload-feedback--error"
                            role="alert"
                        >
                            <strong>No fue posible validar la hoja.</strong>

                            <?php foreach (
                                ($sheetValidation['errors'] ?? []) as $error
                            ): ?>
                                <p>
                                    <?= htmlspecialchars(
                                        (string) $error,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </p>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif (($sheetValidation['valid'] ?? false) === true): ?>
                        <div
                            class="upload-feedback upload-feedback--success"
                            role="status"
                        >
                            <strong>¡Encabezados válidos!</strong>

                            <p>
                                La hoja
                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $sheetValidation['sheet_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                                cumple con la estructura requerida.
                            </p>
                        </div>

                    <?php else: ?>
                        <div
                            class="upload-feedback upload-feedback--error"
                            role="alert"
                        >
                            <strong>
                                La hoja no cumple con los encabezados requeridos.
                            </strong>

                            <?php if (!empty($sheetValidation['missing'])): ?>
                                <p>
                                    <strong>Encabezados faltantes:</strong>
                                </p>

                                <ul>
                                    <?php foreach ($sheetValidation['missing'] as $header): ?>
                                        <li>
                                            <?= htmlspecialchars(
                                                (string) $header,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($sheetValidation['extra'])): ?>
                                <p>
                                    <strong>Encabezados adicionales:</strong>
                                </p>

                                <ul>
                                    <?php foreach ($sheetValidation['extra'] as $header): ?>
                                        <li>
                                            <?= htmlspecialchars(
                                                (string) $header,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($sheetValidation['duplicates'])): ?>
                                <p>
                                    <strong>Encabezados duplicados:</strong>
                                </p>

                                <ul>
                                    <?php foreach ($sheetValidation['duplicates'] as $header): ?>
                                        <li>
                                            <?= htmlspecialchars(
                                                (string) $header,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        <?php endif; ?>

    </section>

    <!-- Presenta las etapas del proceso de selección. -->
    <section
        class="dashboard-section"
        aria-labelledby="workflow-title"
    >
        <div class="dashboard-section__heading">
            <div>
                <h2 id="workflow-title">Proceso de selección</h2>

                <p>
                    El flujo de trabajo que organizaremos
                    progresivamente.
                </p>
            </div>
        </div>

        <div class="workflow-grid">

            <article class="workflow-card">
                <span class="workflow-card__number">01</span>

                <div
                    class="workflow-card__icon"
                    aria-hidden="true"
                >
                    ↑
                </div>

                <h3>Importar estudiantes</h3>

                <p>
                    Cargar un archivo Excel y verificar su estructura
                    antes de iniciar el proceso.
                </p>

                <span class="workflow-card__status">
                    Carga temporal disponible
                </span>
            </article>

            <article class="workflow-card">
                <span class="workflow-card__number">02</span>

                <div
                    class="workflow-card__icon"
                    aria-hidden="true"
                >
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

                <div
                    class="workflow-card__icon"
                    aria-hidden="true"
                >
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

    <!-- Informa sobre el alcance funcional actual. -->
    <aside class="dashboard-notice">
        <span
            class="dashboard-notice__icon"
            aria-hidden="true"
        >
            i
        </span>

        <div>
            <h2>Estado del sistema</h2>

            <p>
                La autenticación y la carga temporal están integradas.
                Ahora puedes inspeccionar las hojas del archivo y validar
                sus encabezados. La selección aleatoria y la exportación
                se implementarán en las siguientes fases.
            </p>
        </div>
    </aside>

</section>

<?php

// Entrega el contenido de la página al layout compartido.
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layout/app.php';
