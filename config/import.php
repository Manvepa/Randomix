<?php

declare(strict_types=1);

/*
 * Centraliza los límites y el esquema de los archivos Excel
 * que Randomix permite importar.
 */
return [
    // Tamaño máximo permitido: 20 MB.
    'max_file_size' => 20 * 1024 * 1024,

    // Cantidad máxima de registros de estudiantes.
    'max_rows' => 20000,

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
