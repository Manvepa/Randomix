<?php

declare(strict_types=1);

namespace Randomix\Controllers;
use Randomix\Services\AdminDashboardService;

final class PageController
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ExcelInspectionController $excelInspectionController,
        private readonly ExcelComparisonController $excelComparisonController,
        private readonly ExcelMergeController $excelMergeController,
        private readonly AdminDashboardService $adminDashboardService
    ) {
    }

    /**
     * Prepara los datos de la página solicitada y carga su vista.
     *
     * La autorización y la validación de las páginas permitidas
     * permanecen en public/index.php.
     */
    public function render(string $page, ?string $errorMessage = null): void
    {
        switch ($page) {
            case 'dashboard':
                require $this->projectRoot . '/templates/dashboard/index.php';
                return;

            case 'admin':
                // Obtiene las estadísticas generales de las operaciones registradas.
                $adminSummary = $this->adminDashboardService->getSummary();

                // Obtiene las diez operaciones más recientes.
                $recentActivity = $this->adminDashboardService->getRecentActivity(10);

                require $this->projectRoot . '/templates/admin/index.php';
                return;

            case 'selection':
                $uploadedExcel = $_SESSION['uploaded_excel'] ?? null;
                $excelSheets = [];
                $sheetValidation = $_SESSION['sheet_validation'] ?? null;
                $selectionResult = $_SESSION['selection_result'] ?? null;

                if (is_array($uploadedExcel)) {
                    $sheetResult = $this->excelInspectionController
                        ->getSheetNames($uploadedExcel);

                    if (($sheetResult['success'] ?? false) === true) {
                        $excelSheets = $sheetResult['sheets'];
                    }
                }

                require $this->projectRoot . '/templates/selection/index.php';
                return;

            case 'comparison':
                $comparisonExcelA = $_SESSION['comparison_excel_a'] ?? null;
                $comparisonExcelB = $_SESSION['comparison_excel_b'] ?? null;
                $comparisonSheetsA = [];
                $comparisonSheetsB = [];
                $comparisonResult = $_SESSION['comparison_result'] ?? null;

                if (is_array($comparisonExcelA)) {
                    $sheetResultA = $this->excelComparisonController
                        ->getSheetNames($comparisonExcelA);

                    if (($sheetResultA['success'] ?? false) === true) {
                        $comparisonSheetsA = $sheetResultA['sheets'];
                    }
                }

                if (is_array($comparisonExcelB)) {
                    $sheetResultB = $this->excelComparisonController
                        ->getSheetNames($comparisonExcelB);

                    if (($sheetResultB['success'] ?? false) === true) {
                        $comparisonSheetsB = $sheetResultB['sheets'];
                    }
                }

                require $this->projectRoot . '/templates/comparison/index.php';
                return;

            case 'merge':
                $mergeExcelA = $_SESSION['merge_excel_a'] ?? null;
                $mergeExcelB = $_SESSION['merge_excel_b'] ?? null;
                $mergeSheetsA = [];
                $mergeSheetsB = [];
                $mergeResult = $_SESSION['merge_result'] ?? null;

                if (is_array($mergeExcelA)) {
                    $sheetResultA = $this->excelMergeController
                        ->getSheetNames($mergeExcelA);

                    if (($sheetResultA['success'] ?? false) === true) {
                        $mergeSheetsA = $sheetResultA['sheets'];
                    }
                }

                if (is_array($mergeExcelB)) {
                    $sheetResultB = $this->excelMergeController
                        ->getSheetNames($mergeExcelB);

                    if (($sheetResultB['success'] ?? false) === true) {
                        $mergeSheetsB = $sheetResultB['sheets'];
                    }
                }

                require $this->projectRoot . '/templates/merge/index.php';
                return;

            case 'login':
                require $this->projectRoot . '/templates/auth/login.php';
                return;
        }
    }
}