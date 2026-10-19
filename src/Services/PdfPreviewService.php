<?php

declare(strict_types=1);

namespace Randomix\Services;

use Com\Tecnick\Pdf\Tcpdf;
use RuntimeException;

final class PdfPreviewService
{
    private const PAGE_WIDTH = 420.0;
    private const PAGE_HEIGHT = 297.0;

    private const MARGIN_LEFT = 8.0;
    private const MARGIN_RIGHT = 8.0;
    private const MARGIN_TOP = 10.0;
    private const MARGIN_BOTTOM = 10.0;

    private const FONT_SIZE = 5.5;
    private const HEADER_FONT_SIZE = 5.5;
    private const MIN_ROW_HEIGHT = 5.0;
    private const LINE_HEIGHT = 3.0;

    /**
     * Genera un PDF A3 horizontal y devuelve su contenido en memoria.
     *
     * @param array $headers Encabezados originales en su orden.
     * @param array $records Registros indexados por encabezado.
     *
     * @throws RuntimeException Si la estructura o generación no es válida.
     */
    public function generate(array $headers, array $records, string $title): string
    {
        $this->validateData($headers, $records);

        // Comprueba que las fuentes generadas por tc-lib-pdf estén disponibles.
        $fontPath = dirname(__DIR__, 2)
            . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts';

        if (!is_dir($fontPath)) {
            throw new RuntimeException(
                'No se encontraron las fuentes necesarias para generar el PDF.'
            );
        }

        if (!defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', realpath($fontPath));
        }

        // Inicializa el documento y define el estilo de las celdas.
        $pdf = new Tcpdf('mm');

        $cellStyles = [
            'all' => [
                'lineWidth' => 0.1,
                'lineColor' => '#808080',
            ],
        ];

        $pageConfig = [
            'format' => 'A3',
            'orientation' => 'L',
            'margin' => [
                'PL' => self::MARGIN_LEFT,
                'PR' => self::MARGIN_RIGHT,
                'CT' => self::MARGIN_TOP,
                'CB' => self::MARGIN_BOTTOM,
            ],
        ];

        // Calcula un ancho uniforme para conservar todas las columnas.
        $availableWidth = self::PAGE_WIDTH
            - self::MARGIN_LEFT
            - self::MARGIN_RIGHT;

        $columnWidth = $availableWidth / count($headers);

        $headerHeight = $this->calculateRowHeight(
            $headers,
            $columnWidth,
            self::HEADER_FONT_SIZE
        );

        // Define el área disponible para las filas de datos.
        $contentBottom = self::PAGE_HEIGHT - self::MARGIN_BOTTOM;
        $y = 0.0;

        // Agrega una página y dibuja nuevamente los encabezados.
        $startPage = function () use (
            $pdf,
            $pageConfig,
            $headers,
            $title,
            $cellStyles,
            $columnWidth,
            $headerHeight
        ): float {
            $page = $pdf->addPage($pageConfig);

            $titleFont = $pdf->font->insert(
                $pdf->pon,
                'helvetica',
                'B',
                10
            );

            $pdf->page->addContent($titleFont['out']);

            $pdf->addTextCellXY(
                txt: $title,
                posx: self::MARGIN_LEFT,
                posy: 4.0,
                width: self::PAGE_WIDTH
                    - self::MARGIN_LEFT
                    - self::MARGIN_RIGHT,
                height: 5.0,
                drawcell: false
            );

            $headerFont = $pdf->font->insert(
                $pdf->pon,
                'helvetica',
                'B',
                self::HEADER_FONT_SIZE
            );

            $pdf->page->addContent($headerFont['out']);

            $x = self::MARGIN_LEFT;
            $headerY = self::MARGIN_TOP;

            foreach ($headers as $header) {
                $pdf->addTextCellXY(
                    txt: $header,
                    posx: $x,
                    posy: $headerY,
                    width: $columnWidth,
                    height: $headerHeight,
                    valign: 'M',
                    halign: 'L',
                    styles: $cellStyles,
                    drawcell: true
                );

                $x += $columnWidth;
            }

            return $headerY + $headerHeight;
        };

        $y = $startPage();

        // Prepara la fuente de los registros.
        $bodyFont = $pdf->font->insert(
            $pdf->pon,
            'helvetica',
            '',
            self::FONT_SIZE
        );

        $pdf->page->addContent($bodyFont['out']);

        // Dibuja los registros respetando el orden original de las columnas.
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new RuntimeException(
                    'Uno de los registros no tiene una estructura válida.'
                );
            }

            $values = [];

            foreach ($headers as $header) {
                $values[] = $this->formatValue($record[$header] ?? null);
            }

            $rowHeight = $this->calculateRowHeight(
                $values,
                $columnWidth,
                self::FONT_SIZE
            );

            // Evita comenzar una fila si no cabe completa en la página actual.
            if ($y + $rowHeight > $contentBottom) {
                $y = $startPage();
                $pdf->page->addContent($bodyFont['out']);
            }

            $x = self::MARGIN_LEFT;

            foreach ($values as $value) {
                $pdf->addTextCellXY(
                    txt: $value,
                    posx: $x,
                    posy: $y,
                    width: $columnWidth,
                    height: $rowHeight,
                    valign: 'T',
                    halign: 'L',
                    styles: $cellStyles,
                    drawcell: true
                );

                $x += $columnWidth;
            }

            $y += $rowHeight;
        }

        // Devuelve los bytes del PDF para que el controlador los envíe al navegador.
        $content = $pdf->getOutPDFString();

        if ($content === '' || !str_starts_with($content, '%PDF-')) {
            throw new RuntimeException(
                'No fue posible generar un documento PDF válido.'
            );
        }

        return $content;
    }

    /**
     * Comprueba los encabezados y la estructura de los registros.
     */
    private function validateData(array $headers, array $records): void
    {
        if ($headers === []) {
            throw new RuntimeException(
                'No hay encabezados disponibles para generar el PDF.'
            );
        }

        foreach ($headers as $header) {
            if (!is_string($header) || trim($header) === '') {
                throw new RuntimeException(
                    'La estructura de los encabezados no es válida.'
                );
            }
        }

        if (count(array_unique($headers)) !== count($headers)) {
            throw new RuntimeException(
                'Los encabezados no pueden estar duplicados.'
            );
        }

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new RuntimeException(
                    'Uno de los registros no tiene una estructura válida.'
                );
            }
        }
    }

    /**
     * Convierte los valores admitidos en texto legible para el PDF.
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new RuntimeException(
            'Uno de los valores no es compatible con el PDF.'
        );
    }

    /**
     * Estima la altura de una fila según el texto y el ancho de sus columnas.
     */
    private function calculateRowHeight(
        array $values,
        float $columnWidth,
        float $fontSize
    ): float {
        // Aproxima los caracteres por línea para ajustar la altura de las celdas.
        $charactersPerLine = max(
            1,
            (int) floor($columnWidth * 0.8 * (5.5 / $fontSize))
        );

        $maxLines = 1;

        foreach ($values as $value) {
            $text = (string) $value;
            $lines = 0;

            foreach (explode("\n", wordwrap(
                $text,
                $charactersPerLine,
                "\n",
                true
            )) as $line) {
                $lines++;
            }

            $maxLines = max($maxLines, $lines);
        }

        return max(
            self::MIN_ROW_HEIGHT,
            ($maxLines * self::LINE_HEIGHT) + 1.0
        );
    }
}
