<?php

declare(strict_types=1);

/*
 * Centraliza los límites y los formatos de los archivos Excel
 * que Randomix permite recibir.
 */
return [
    // Tamaño máximo permitido por la aplicación: 20 MiB.
    'max_file_size' => 20 * 1024 * 1024,

    // Cantidad máxima de registros de estudiantes.
    'max_rows' => 20000,

    /*
     * MIME que pueden aparecer en Windows/XAMPP y otros entornos.
     *
     * XLSX es un contenedor ZIP basado en OOXML. La identificación
     * definitiva se realiza también con PhpSpreadsheet; por eso
     * aceptar application/zip aquí no significa aceptar cualquier ZIP.
     *
     * XLS utiliza el contenedor binario OLE/CFB.
     */
    'allowed_mime_types' => [
        'xlsx' => [
            'application/zip',
            'application/x-zip',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'xls' => [
            'application/vnd.ms-excel',
            'application/x-ole-storage',
            'application/x-cfb',
            'application/cdfv2',
            'application/x-cdf',
        ],
    ],

    // Encabezados oficiales requeridos para importar estudiantes.
    'required_headers' => [
        'ESTADO_ALUMNO',
        'DANE',
        'INSTITUCION',
        'SEDE',
        'GRADO',
        'JORNADA',
        'tipo_doc',
        'doc',
        'apellido_1',
        'apellido_2',
        'nombre_1',
        'nombre_2',
        'DIRECCION',
        'edad',
        'FECHA_INGRESO AL MUNICIPIO',
        'TELEFONO',
        'BARRIO',
        'ACUDIENTE Tipo Doc',
        'ACUDIENTE Doc',
        'ACUDIENTES',
    ],
];
