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

        $postActionController = new PostActionController(
            $uploadController,
            $excelInspectionController,
            $selectionController,
            $excelComparisonController,
            $excelMergeController
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
