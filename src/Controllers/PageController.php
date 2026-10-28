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
                // Obtiene las estadísticas generales de Randomix.
                $adminSummary = $this->adminDashboardService->getSummary();

                // Determina si se solicitó consultar el historial completo.
                $showFullHistory = ($_GET['history'] ?? '') === '1';

                if ($showFullHistory) {
                    // Valida el número de página solicitado.
                    $requestedPage = filter_var(
                        $_GET['p'] ?? 1,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    );

                    $currentPage = $requestedPage === false
                        ? 1
                        : $requestedPage;

                    // Define el tamaño de cada página.
                    $perPage = 20;

                    // Calcula el total de páginas disponibles.
                    $totalActivity = $this->adminDashboardService
                        ->getActivityCount();

                    $totalPages = max(
                        1,
                        (int) ceil($totalActivity / $perPage)
                    );

                    // Evita solicitar páginas superiores al total disponible.
                    $currentPage = min($currentPage, $totalPages);

                    // Obtiene las operaciones de la página solicitada.
                    $recentActivity = $this->adminDashboardService
                        ->getActivityPage($currentPage, $perPage);
                } else {
                    // Conserva las diez operaciones recientes.
                    $recentActivity = $this->adminDashboardService
                        ->getRecentActivity(10);

                    $currentPage = 1;
                    $perPage = 10;
                    $totalActivity = count($recentActivity);
                    $totalPages = 1;
                }

                require $this->projectRoot . '/templates/admin/index.php';
                return;

            case 'selection':

        }
    }
}