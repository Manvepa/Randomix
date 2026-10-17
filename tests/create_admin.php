<?php

declare(strict_types=1);

use Randomix\Support\Database;

// Carga el autoloader y las variables de entorno de Randomix.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/app.php';

/**
 * Solicita la contraseña sin mostrar los caracteres escritos.
 * En Windows utiliza PowerShell para ocultar la entrada.
 */
function solicitarContrasena(): string
{
    if (PHP_OS_FAMILY !== 'Windows') {
        throw new RuntimeException(
            'Este script está preparado para ejecutarse en Windows.'
        );
    }

    $comando = [
        'powershell.exe',
        '-NoProfile',
        '-Command',
        '$p = Read-Host "Introduce la contraseña de Tics" -AsSecureString; ' .
        '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); ' .
        'try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b) } ' .
        'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b) }',
    ];

    $argumentos = implode(
        ' ',
        array_map(
            static fn(string $arg): string =>
                '"' . str_replace('"', '\"', $arg) . '"',
            $comando
        )
    );

    $salida = [];
    $codigo = 0;

    exec($argumentos, $salida, $codigo);

    if ($codigo !== 0 || !isset($salida[0]) || $salida[0] === '') {
        throw new RuntimeException(
            'No se recibió una contraseña válida.'
        );
    }

    return $salida[0];
}

// Crea el usuario solo si todavía no existe.
try {
    $pdo = Database::getConnection();

    $consulta = $pdo->prepare(
        'SELECT id FROM admin_users WHERE username = :username'
    );
    $consulta->execute(['username' => 'Tics']);

    if ($consulta->fetch()) {
        throw new RuntimeException(
            'El usuario Tics ya existe. No se realizaron cambios.'
        );
    }

    $contrasena = solicitarContrasena();

    // Genera un hash seguro; nunca se almacena la contraseña original.
    $hash = password_hash($contrasena, PASSWORD_DEFAULT);

    if ($hash === false) {
        throw new RuntimeException(
            'No fue posible generar el hash de la contraseña.'
        );
    }

    $insertar = $pdo->prepare(
        'INSERT INTO admin_users (username, password_hash)
         VALUES (:username, :password_hash)'
    );

    $insertar->execute([
        'username' => 'Tics',
        'password_hash' => $hash,
    ]);

    echo "Administrador Tics creado correctamente.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Error: {$exception->getMessage()}\n");
    exit(1);
}
