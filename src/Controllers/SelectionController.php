<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Import\ExcelReader;
use Randomix\Import\StudentRecordsProcessor;
use Randomix\Services\SelectionService;
use Randomix\Validators\ExcelHeadersValidator;
use Randomix\Services\SurnameMatchService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SelectionController
{
    private string $temporaryDirectory;

    private ExcelReader $excelReader;

    private StudentRecordsProcessor $recordsProcessor;

    private SelectionService $selectionService;

    private SurnameMatchService $surnameMatchService;

    private ExcelHeadersValidator $headersValidator;

    /**
     * Inicializa los componentes necesarios para procesar el Excel
     * y seleccionar registros.
     */
    public function __construct(
        string $projectRoot,
        array $requiredHeaders
    ) {
        $this->temporaryDirectory = rtrim(
            $projectRoot,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'temporary';

        $this->excelReader = new ExcelReader();
        $this->recordsProcessor = new StudentRecordsProcessor();
        $this->selectionService = new SelectionService();
        $this->surnameMatchService = new SurnameMatchService();

        $this->headersValidator = new ExcelHeadersValidator(
            $requiredHeaders
        );
    }

    /**
     * Procesa una hoja validada y separa los registros seleccionados
     * de los que no fueron seleccionados.
     */
    public function handle(
        array $uploadedExcel,
        string $sheetName,
        int $beneficiaryQuantity,
        int $alternateQuantity,
        array $sheetValidation
    ): array {
        try {
            // Exige que la hoja haya superado la validación previa.
            if (
                ($sheetValidation['success'] ?? false) !== true
                || ($sheetValidation['valid'] ?? false) !== true
                || ($sheetValidation['sheet_name'] ?? null) !== $sheetName
            ) {
                throw new RuntimeException(
                    'Debes validar correctamente la hoja antes de seleccionar.'
                );
            }

            // Comprueba nuevamente que el archivo temporal siga vigente.
            $filePath = $this->getValidFilePath($uploadedExcel);

            // Revalida los encabezados del archivo que se va a procesar.
            $headers = $this->excelReader->getHeaders(
                $filePath,
                $sheetName
            );

            $validation = $this->headersValidator->validate($headers);

            if ($validation['valid'] !== true) {
                throw new RuntimeException(
                    'Los encabezados de la hoja ya no son válidos.'
                );
            }


            // Lee las filas conservando el orden original de las columnas.
            $rows = $this->excelReader->getRows(
                $filePath,
                $sheetName
            );

            // Busca la columna opcional de numeración agregada por Randomix.
            $numberColumnIndex = array_search(
                '#',
                array_map(
                    static fn(mixed $header): string => trim(
                        (string) ($header ?? '')
                    ),
                    $headers
                ),
                true
            );

            // Retira la columna # de los encabezados y de todas las filas.
            // Así, los datos vuelven a coincidir con el esquema oficial.
            if ($numberColumnIndex !== false) {
                array_splice($headers, $numberColumnIndex, 1);

                foreach ($rows as &$row) {
                    if (
                        is_array($row)
                        && array_key_exists($numberColumnIndex, $row)
                    ) {
                        array_splice(
                            $row,
                            $numberColumnIndex,
                            1
                        );
                    }
                }

                unset($row);
            }

            // Procesa únicamente los encabezados y datos oficiales.
            $records = $this->recordsProcessor->process(
                $headers,
                $rows
            );

            // Realiza un único sorteo y separa beneficiarios,
            // suplentes y registros no seleccionados.
            $result = $this->selectionService->select(
                $records,
                $beneficiaryQuantity,
                $alternateQuantity
            );

            // Numera los beneficiarios con el prefijo B.
            $beneficiaryRecords = $this->addSequentialNumberColumn(
                $result['beneficiaries'],
                'B'
            );

            // Numera los suplentes con el prefijo S.
            $alternateRecords = $this->addSequentialNumberColumn(
                $result['alternates'],
                'S'
            );

            // Conserva ambos grupos en una única lista de seleccionados.
            // Primero aparecen los beneficiarios y después los suplentes.
            $selectedRecords = array_merge(
                $beneficiaryRecords,
                $alternateRecords
            );

            // Los registros restantes conservan una numeración independiente.
            $notSelectedRecords = $this->addSequentialNumberColumn(
                $result['not_selected']
            );

            // Detecta coincidencias de apellidos dentro del grupo seleccionado.
            $matchingSurnameIndexes = $this->surnameMatchService
                ->findMatchingIndexes($selectedRecords);

            return [
                'success' => true,
                'errors' => [],
                'sheet_name' => $sheetName,
                'total_records' => count($records),
                'beneficiaries_count' => count($beneficiaryRecords),
                'alternates_count' => count($alternateRecords),
                'selected_count' => count($selectedRecords),
                'not_selected_count' => count($notSelectedRecords),
                'beneficiaries' => $beneficiaryRecords,
                'alternates' => $alternateRecords,
                'selected' => $selectedRecords,
                'not_selected' => $notSelectedRecords,
                'selected_matching_surname_indexes' => $matchingSurnameIndexes,
            ];


            // Agrega una numeración consecutiva independiente a cada resultado.
            $selectedRecords = $this->addSequentialNumberColumn(
                $result['selected']
            );

            $notSelectedRecords = $this->addSequentialNumberColumn(
                $result['not_selected']
            );

            // Identifica coincidencias de apellidos exclusivamente entre seleccionados.
            $matchingSurnameIndexes = $this->surnameMatchService
                ->findMatchingIndexes($selectedRecords);

            return [
                'success' => true,
                'errors' => [],
                'sheet_name' => $sheetName,
                'total_records' => count($records),
                'selected_count' => count($result['selected']),
                'not_selected_count' => count($result['not_selected']),
                'selected' => $selectedRecords,
                'not_selected' => $notSelectedRecords,
                'selected_matching_surname_indexes' => $matchingSurnameIndexes,
            ];
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return [
                'success' => false,
                'errors' => [$exception->getMessage()],
            ];
        } catch (Throwable $exception) {
            // Registra los detalles técnicos sin exponerlos en el panel.
            error_log($exception->__toString());

            return [
                'success' => false,
                'errors' => [
                    'No fue posible procesar el Excel y realizar la selección.',
                ],
            ];
        }
    }

    /**
     * Verifica la vigencia, el nombre y la ubicación del archivo temporal.
     */
    private function getValidFilePath(array $uploadedExcel): string
    {
        $filename = $uploadedExcel['filename'] ?? null;
        $expiresAt = $uploadedExcel['expires_at'] ?? null;

        if (
            !is_string($filename)
            || !preg_match(
                '/^randomix_upload_[a-f0-9]{32}\.(xlsx|xls)$/i',
                $filename
            )
            || !is_int($expiresAt)
            || $expiresAt <= time()
        ) {
            throw new RuntimeException(
                'El archivo temporal no existe o ha vencido. Cárgalo nuevamente.'
            );
        }

        $filePath = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException(
                'El archivo temporal no está disponible. Cárgalo nuevamente.'
            );
        }

        return $filePath;
    }

    /**
     * Agrega la columna # al comienzo de cada registro.
     *
     * Permite numerar beneficiarios con B, suplentes con S
     * y otros grupos con numeración simple.
     *
     * @param array<int, array<string, mixed>> $records Registros del grupo.
     * @param string $prefix Prefijo opcional para la numeración.
     *
     * @return array<int, array<string, mixed>> Registros numerados.
     */
    private function addSequentialNumberColumn(
        array $records,
        string $prefix = ''
    ): array {
        foreach ($records as $index => $record) {
            // Construye el identificador y lo coloca antes de los datos.
            $number = $prefix . ($index + 1);

            $records[$index] = ['#' => $number] + $record;
        }

        return $records;
    }

}
