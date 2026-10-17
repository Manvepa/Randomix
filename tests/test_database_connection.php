<?php
declare(strict_types=1);

use Randomix\Support\Database;

// Carga las clases del proyecto y las variables de entorno.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/app.php';

// Prueba la conexión y consulta la versión del servidor sin modificar datos.
try {
    $pdo = Database::getConnection();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();

    echo "Conexión correcta con Randomix.\n";
    echo "Versión del servidor: {$version}\n";
} catch (Throwable $exception) {
    // Informa del error en la terminal para facilitar el diagnóstico local.
    fwrite(STDERR, "Error al conectar: {$exception->getMessage()}\n");
    exit(1);
}