<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Throwable;

final class TemporaryFileCleanupController
{
    public function __construct(
        private readonly UploadController $uploadController
    ) {
    }

    /**
     * Elimina archivos físicos vencidos y limpia las referencias de sesión.
     */
    public function cleanup(): void
    {
        try {
            $this->uploadController->cleanupExpiredFiles();
        } catch (Throwable $exception) {
            error_log($exception->__toString());
        }

        $this->cleanupSelectionFile();
        $this->cleanupComparisonFiles();
    }

    /**
     * Invalida el archivo y los resultados del proceso de selección si venció.
     */
    private function cleanupSelectionFile(): void
    {
        $uploadedExcel = $_SESSION['uploaded_excel'] ?? null;

        if (
            !is_array($uploadedExcel)
            || !isset($uploadedExcel['expires_at'])
            || !is_int($uploadedExcel['expires_at'])
            || $uploadedExcel['expires_at'] > time()
        ) {
            return;
        }

        unset($_SESSION['uploaded_excel']);
        unset($_SESSION['sheet_validation']);
        unset($_SESSION['selection_result']);

        $_SESSION['upload_feedback'] = [
            'type' => 'error',
            'message' => 'El archivo temporal venció. Debes cargarlo nuevamente.',
        ];
    }

    /**
     * Invalida la comparación cuando vence cualquiera de sus archivos.
     */
    private function cleanupComparisonFiles(): void
    {
        $comparisonExpired = false;

        foreach (['comparison_excel_a', 'comparison_excel_b'] as $sessionKey) {
            $comparisonFile = $_SESSION[$sessionKey] ?? null;

            if (
                is_array($comparisonFile)
                && isset($comparisonFile['expires_at'])
                && is_int($comparisonFile['expires_at'])
                && $comparisonFile['expires_at'] <= time()
            ) {
                unset($_SESSION[$sessionKey]);
                $comparisonExpired = true;
            }
        }

        if (!$comparisonExpired) {
            return;
        }

        unset($_SESSION['comparison_result']);

        $_SESSION['comparison_feedback'] = [
            'type' => 'error',
            'message' => 'Uno de los archivos de comparación ha vencido. Debes cargarlo nuevamente.',
        ];
    }
}
