<?php

declare(strict_types=1);

namespace Randomix\Services;

use PDOException;
use Randomix\Support\Database;
use RuntimeException;

final class RegistrationService
{
    /**
     * Crea una cuenta nueva después de validar sus datos.
     *
     * Devuelve un mensaje de error si los datos no son válidos
     * o si el nombre de usuario ya está registrado.
     */
    public function register(
        string $username,
        string $password,
        string $passwordConfirmation
    ): ?string {
        // Normaliza el nombre de usuario antes de validarlo.
        $username = trim($username);

        if ($username === '' || strlen($username) > 50) {
            return 'El usuario debe tener entre 1 y 50 caracteres.';
        }

        // Limita los nombres a caracteres sencillos y previsibles.
        if (!preg_match('/\A[a-zA-Z0-9._-]+\z/', $username)) {
            return 'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos.';
        }

        // Exige una contraseña suficientemente larga.
        if (strlen($password) < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (strlen($password) > 4096) {
            return 'La contraseña supera la longitud permitida.';
        }

        // Comprueba que ambas contraseñas coincidan exactamente.
        if ($password !== $passwordConfirmation) {
            return 'Las contraseñas no coinciden.';
        }

        // Genera un hash seguro; nunca almacena la contraseña original.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException(
                'No fue posible proteger la contraseña.'
            );
        }

        $pdo = Database::getConnection();

        try {
            // El índice único de la base de datos evita usuarios duplicados.
            $consulta = $pdo->prepare(
                'INSERT INTO admin_users (username, password_hash)
                 VALUES (:username, :password_hash)'
            );

            $consulta->execute([
                'username' => $username,
                'password_hash' => $passwordHash,
            ]);
        } catch (PDOException $exception) {
            // El error 1062 de MariaDB indica una clave duplicada.
            if (
                $exception->getCode() === '23000'
                && (int) ($exception->errorInfo[1] ?? 0) === 1062
            ) {
                return 'No se pudo crear la cuenta con esos datos.';
            }

            throw $exception;
        }

        return null;
    }
}
