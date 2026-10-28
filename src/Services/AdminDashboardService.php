<?php

declare(strict_types=1);

namespace Randomix\Services;

use PDO;

final class AdminDashboardService
{
    /**
     * Recibe la conexión PDO compartida por Randomix.
     */
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Obtiene la cantidad total de operaciones registradas
     * para cada uno de los procesos de Randomix.
     *
     * @return array{
     *     selections: int,
     *     comparisons: int,
     *     merges: int
     * }
     */
    public function getSummary(): array
    {
        $sql = <<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM selection_runs) AS selections,
                (SELECT COUNT(*) FROM comparison_runs) AS comparisons,
                (SELECT COUNT(*) FROM excel_merge_runs) AS merges
            SQL;

        $statement = $this->connection->query($sql);
        $summary = $statement->fetch(PDO::FETCH_ASSOC);

        return [
            'selections' => (int) ($summary['selections'] ?? 0),
            'comparisons' => (int) ($summary['comparisons'] ?? 0),
            'merges' => (int) ($summary['merges'] ?? 0),
        ];
    }

    /**
     * Obtiene las operaciones más recientes junto con los
     * archivos generados asociados a cada operación.
     *
     * En una selección pueden existir tres archivos:
     * - Beneficiarios seleccionados.
     * - Estudiantes no seleccionados.
     * - Estudiantes alternos.
     *
     * En comparación y unión se recupera el archivo resultado.
     *
     * @return list<array<string, mixed>>
     */
    public function getRecentActivity(int $limit = 10): array
    {
        // Mantiene el límite dentro de un rango seguro.
        $limit = max(1, min($limit, 100));

        // Unifica las operaciones y recupera los metadatos
        // de sus archivos desde file_artifacts.
        $sql = <<<'SQL'
            SELECT
                sr.id AS operation_id,
                'Selección' AS operation_type,
                sr.executed_at AS operation_date,
                sr.total_records AS records_processed,
                sr.beneficiaries_count AS result_count,

                NULL AS result_file_id,
                NULL AS result_file_name,
                NULL AS result_file_extension,

                sr.selected_file_id AS selected_file_id,
                sf.original_name AS selected_file_name,
                sf.file_extension AS selected_file_extension,

                sr.not_selected_file_id AS not_selected_file_id,
                nf.original_name AS not_selected_file_name,
                nf.file_extension AS not_selected_file_extension,

                sr.alternates_file_id AS alternates_file_id,
                af.original_name AS alternates_file_name,
                af.file_extension AS alternates_file_extension

            FROM selection_runs AS sr

            LEFT JOIN file_artifacts AS sf
                ON sf.id = sr.selected_file_id

            LEFT JOIN file_artifacts AS nf
                ON nf.id = sr.not_selected_file_id

            LEFT JOIN file_artifacts AS af
                ON af.id = sr.alternates_file_id

            UNION ALL

            SELECT
                cr.id AS operation_id,
                'Comparación' AS operation_type,
                cr.executed_at AS operation_date,
                cr.total_original AS records_processed,
                cr.remaining AS result_count,

                cr.result_file_id AS result_file_id,
                rf.original_name AS result_file_name,
                rf.file_extension AS result_file_extension,

                NULL AS selected_file_id,
                NULL AS selected_file_name,
                NULL AS selected_file_extension,

                NULL AS not_selected_file_id,
                NULL AS not_selected_file_name,
                NULL AS not_selected_file_extension,

                NULL AS alternates_file_id,
                NULL AS alternates_file_name,
                NULL AS alternates_file_extension

            FROM comparison_runs AS cr

            LEFT JOIN file_artifacts AS rf
                ON rf.id = cr.result_file_id

            UNION ALL

            SELECT
                mr.id AS operation_id,
                'Unión' AS operation_type,
                mr.executed_at AS operation_date,
                mr.total_a + mr.total_b AS records_processed,
                mr.total_merged AS result_count,

                mr.result_file_id AS result_file_id,
                mf.original_name AS result_file_name,
                mf.file_extension AS result_file_extension,

                NULL AS selected_file_id,
                NULL AS selected_file_name,
                NULL AS selected_file_extension,

                NULL AS not_selected_file_id,
                NULL AS not_selected_file_name,
                NULL AS not_selected_file_extension,

                NULL AS alternates_file_id,
                NULL AS alternates_file_name,
                NULL AS alternates_file_extension

            FROM excel_merge_runs AS mr

            LEFT JOIN file_artifacts AS mf
                ON mf.id = mr.result_file_id

            ORDER BY operation_date DESC, operation_id DESC
            SQL;

        // Ejecuta la consulta.
        $statement = $this->connection->query($sql);

        // Recupera las operaciones ordenadas.
        $activities = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Limita los registros devueltos al controlador.
        return array_slice($activities, 0, $limit);
    }


    /**
     * Obtiene una página del historial completo de operaciones.
     *
     * @return list<array<string, mixed>>
     */
    public function getActivityPage(
        int $page = 1,
        int $perPage = 20
    ): array {
        // Normaliza la paginación para evitar valores inválidos.
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));

        // Calcula cuántos registros se deben omitir.
        $offset = ($page - 1) * $perPage;

        // Obtiene las operaciones ordenadas por fecha e identificador.
        $activities = $this->getAllActivity();

        return array_slice($activities, $offset, $perPage);
    }

    /**
     * Obtiene todas las operaciones con sus archivos asociados.
     *
     * Este método reutiliza la estructura de la consulta del historial
     * existente para conservar los enlaces de descarga.
     *
     * @return list<array<string, mixed>>
     */
    private function getAllActivity(): array
    {
        // Consulta reutilizada de getRecentActivity().
        $sql = <<<'SQL'
            SELECT
                sr.id AS operation_id,
                'Selección' AS operation_type,
                sr.executed_at AS operation_date,
                sr.total_records AS records_processed,
                sr.beneficiaries_count AS result_count,

                NULL AS result_file_id,
                NULL AS result_file_name,
                NULL AS result_file_extension,

                sr.selected_file_id AS selected_file_id,
                sf.original_name AS selected_file_name,
                sf.file_extension AS selected_file_extension,

                sr.not_selected_file_id AS not_selected_file_id,
                nf.original_name AS not_selected_file_name,
                nf.file_extension AS not_selected_file_extension,

                sr.alternates_file_id AS alternates_file_id,
                af.original_name AS alternates_file_name,
                af.file_extension AS alternates_file_extension

            FROM selection_runs AS sr
            LEFT JOIN file_artifacts AS sf
                ON sf.id = sr.selected_file_id
            LEFT JOIN file_artifacts AS nf
                ON nf.id = sr.not_selected_file_id
            LEFT JOIN file_artifacts AS af
                ON af.id = sr.alternates_file_id

            UNION ALL

            SELECT
                cr.id AS operation_id,
                'Comparación' AS operation_type,
                cr.executed_at AS operation_date,
                cr.total_original AS records_processed,
                cr.remaining AS result_count,

                cr.result_file_id AS result_file_id,
                rf.original_name AS result_file_name,
                rf.file_extension AS result_file_extension,

                NULL AS selected_file_id,
                NULL AS selected_file_name,
                NULL AS selected_file_extension,

                NULL AS not_selected_file_id,
                NULL AS not_selected_file_name,
                NULL AS not_selected_file_extension,

                NULL AS alternates_file_id,
                NULL AS alternates_file_name,
                NULL AS alternates_file_extension

            FROM comparison_runs AS cr
            LEFT JOIN file_artifacts AS rf
                ON rf.id = cr.result_file_id

            UNION ALL

            SELECT
                mr.id AS operation_id,
                'Unión' AS operation_type,
                mr.executed_at AS operation_date,
                mr.total_a + mr.total_b AS records_processed,
                mr.total_merged AS result_count,

                mr.result_file_id AS result_file_id,
                mf.original_name AS result_file_name,
                mf.file_extension AS result_file_extension,

                NULL AS selected_file_id,
                NULL AS selected_file_name,
                NULL AS selected_file_extension,

                NULL AS not_selected_file_id,
                NULL AS not_selected_file_name,
                NULL AS not_selected_file_extension,

                NULL AS alternates_file_id,
                NULL AS alternates_file_name,
                NULL AS alternates_file_extension

            FROM excel_merge_runs AS mr
            LEFT JOIN file_artifacts AS mf
                ON mf.id = mr.result_file_id

            ORDER BY operation_date DESC, operation_id DESC
            SQL;

        $statement = $this->connection->query($sql);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta todas las operaciones para calcular las páginas disponibles.
     */
    public function getActivityCount(): int
    {
        $sql = <<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM selection_runs)
                + (SELECT COUNT(*) FROM comparison_runs)
                + (SELECT COUNT(*) FROM excel_merge_runs)
                AS total
            SQL;

        return (int) $this->connection->query($sql)->fetchColumn();
    }

}
