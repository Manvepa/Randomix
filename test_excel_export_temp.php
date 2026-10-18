<?php

require 'vendor/autoload.php';

$service = new Randomix\Services\ExcelExportService();

$content = $service->generate(
    ['doc', 'nombre_1'],
    [
        [
            'doc' => '12345',
            'nombre_1' => 'Estudiante de prueba',
        ],
    ]
);

echo 'Bytes generados: ' . strlen($content) . PHP_EOL;

$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'randomix_test.xlsx';

file_put_contents($path, $content);

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();

echo 'Encabezados: '
    . $sheet->getCell('A1')->getValue()
    . ' | '
    . $sheet->getCell('B1')->getValue()
    . PHP_EOL;

echo 'Registro: '
    . $sheet->getCell('A2')->getValue()
    . ' | '
    . $sheet->getCell('B2')->getValue()
    . PHP_EOL;

$spreadsheet->disconnectWorksheets();
unlink($path);

echo 'Prueba finalizada correctamente.' . PHP_EOL;
