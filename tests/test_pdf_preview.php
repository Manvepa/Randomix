<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Prepara datos ficticios para comprobar el servicio PDF.
$service = new \Randomix\Services\PdfPreviewService();

$headers = ['DANE', 'NOMBRE'];

$records = [
    [
        'DANE' => '00123',
        'NOMBRE' => 'Estudiante de prueba',
    ],
    [
        'DANE' => '00456',
        'NOMBRE' => 'Segundo estudiante de prueba',
    ],
];

// Genera el PDF y lo guarda temporalmente fuera del proyecto.
$pdf = $service->generate(
    $headers,
    $records,
    'Prueba de PDF - Randomix'
);

$outputPath = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'randomix-pdf-test.pdf';

if (file_put_contents($outputPath, $pdf) === false) {
    throw new RuntimeException('No fue posible guardar el PDF de prueba.');
}

echo 'PDF generado correctamente.' . PHP_EOL;
echo 'Bytes: ' . strlen($pdf) . PHP_EOL;
echo 'Ruta: ' . $outputPath . PHP_EOL;
