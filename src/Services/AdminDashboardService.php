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
     * Obtiene las operaciones más recientes de selección,
     * comparación y unión de archivos Excel.
     *
     * El límite se valida antes de consultar la base de datos.
     *
     * @return list<array{
     *     operation_id: int,
     *     operation_type: string,
     *     operation_date: string,
     *     records_processed: int,
     *     result_count: int
     * }>
     */
    public function getRecentActivity(int $limit = 10): array
    {
        // Mantiene el límite dentro de un rango seguro.
        $limit = max(1, min($limit, 100));

        // Unifica las operaciones y las ordena de más reciente a más antigua.
        $sql = <<<'SQL'
            SELECT
                id AS operation_id,
                'Selección' AS operation_type,
                executed_at AS operation_date,
                total_records AS records_processed,
                beneficiaries_count AS result_count
            FROM selection_runs

            UNION ALL

            SELECT
                id AS operation_id,
                'Comparación' AS operation_type,
                executed_at AS operation_date,
                total_original AS records_processed,
                remaining AS result_count
            FROM comparison_runs

            UNION ALL

            SELECT
                id AS operation_id,
                'Unión' AS operation_type,
                executed_at AS operation_date,
                total_a + total_b AS records_processed,
                total_merged AS result_count
            FROM excel_merge_runs

            ORDER BY operation_date DESC, operation_id DESC
            SQL;

        // Ejecuta la consulta sin interpolar variables dentro del SQL.
        $statement = $this->connection->query($sql);

        // Recupera las operaciones ordenadas.
        $activities = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Limita los registros devueltos al controlador.
        return array_slice($activities, 0, $limit);
    }
}
