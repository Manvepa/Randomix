<?php

declare(strict_types=1);

namespace Randomix\Support;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    // Abre una conexión PDO utilizando la configuración de la aplicación.
    public static function getConnection(): PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        // Evita intentar una conexión con parámetros incompletos.
        if (
            $config['connection'] !== 'mysql'
            || $config['database'] === ''
            || $config['username'] === ''
        ) {
            throw new RuntimeException(
                'La configuración de la base de datos está incompleta.'
            );
        }

        // Construye el DSN con el servidor, puerto, base de datos y charset.
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['connection'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        // Configura PDO para informar errores y devolver resultados asociativos.
        try {
            return new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            // Evita mostrar credenciales o detalles internos al usuario final.
            throw new RuntimeException(
                'No fue posible conectar con la base de datos.',
                0,
                $exception
            );
        }
    }
}
