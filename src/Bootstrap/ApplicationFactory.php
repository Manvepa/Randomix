<?php

declare(strict_types=1);

namespace Randomix\Bootstrap;

use Randomix\Controllers\ExcelComparisonController;
use Randomix\Controllers\ExcelInspectionController;
use Randomix\Controllers\ExcelMergeController;
use Randomix\Controllers\PostActionController;
use Randomix\Controllers\SelectionController;
use Randomix\Controllers\TemporaryFileCleanupController;
use Randomix\Controllers\UploadController;
use Randomix\Services\ComparisonPersistenceService;
use Randomix\Support\Database;
use Randomix\Services\SelectionPersistenceService;
use Randomix\Services\ExcelExportService;
use Randomix\Services\FileArtifactService;

final class ApplicationFactory
{
    /**
     * Construye los controladores y sus dependencias principales.
     *
     * Centraliza la configuración que antes estaba en public/index.php
     * sin modificar la lógica de los procesos de Randomix.
     */
    public static function create(string $projectRoot): array
    {
        $importConfig = require $projectRoot . '/config/import.php';

        $maxFileSize = (int) $importConfig['max_file_size'];
        $maxRows = (int) $importConfig['max_rows'];
        $requiredHeaders = $importConfig['required_headers'];

        $uploadController = new UploadController(
            $projectRoot,
            $maxFileSize
        );

        $temporaryFileCleanupController =
            new TemporaryFileCleanupController($uploadController);

        $excelComparisonController = new ExcelComparisonController(
            $projectRoot,
            $maxFileSize,
            $requiredHeaders,
            $maxRows
        );

        $excelMergeController = new ExcelMergeController(
            $projectRoot,
            $maxFileSize,
            $requiredHeaders,
            $maxRows
        );

        $excelInspectionController = new ExcelInspectionController(
            $projectRoot,
            $requiredHeaders
        );

        $selectionController = new SelectionController(
            $projectRoot,
            $requiredHeaders
        );

        // Obtiene una única conexión para compartirla entre los servicios.
        $connection = Database::getConnection();

        // Prepara el servicio encargado de persistir las comparaciones.
        $comparisonPersistenceService = new ComparisonPersistenceService(
            $connection
        );

        // Prepara el servicio encargado de persistir las selecciones.
        $selectionPersistenceService = new SelectionPersistenceService(
            $connection
        );

        // Prepara el servicio encargado de generar los archivos Excel.
        $excelExportService = new ExcelExportService();

        // Prepara el almacenamiento definitivo con la conexión compartida.
        $fileArtifactService = new FileArtifactService(
            $projectRoot,
            $connection
        );

        $postActionController = new PostActionController(
            $uploadController,
            $excelInspectionController,
            $selectionController,
            $excelComparisonController,
            $excelMergeController,
            $comparisonPersistenceService,
            $selectionPersistenceService,
            $excelExportService,
            $fileArtifactService
        );

        return [
            'temporary_file_cleanup' => $temporaryFileCleanupController,
            'excel_comparison' => $excelComparisonController,
            'excel_merge' => $excelMergeController,
            'excel_inspection' => $excelInspectionController,
            'post_action' => $postActionController,
        ];
    }
}
