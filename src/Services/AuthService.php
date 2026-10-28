<?php

declare(strict_types=1);

namespace Randomix\Services;

use Randomix\Support\Database;
use PDO;

final class AuthService
{
    /**
     * Verifica las credenciales del administrador.
     *
     * Si son correctas, actualiza la fecha del último acceso
     * y devuelve los datos básicos necesarios para la sesión.
     * Si son incorrectas, devuelve null.
     */
    public function authenticate(
        string $username,
        string $password
    ): ?array {
        // Busca al administrador mediante una consulta preparada.
        $pdo = Database::getConnection();

        // Recupera el rol junto con las credenciales del usuario.
        $consulta = $pdo->prepare(
            'SELECT id, username, password_hash, rol
            FROM admin_users
            WHERE username = :username
            LIMIT 1'
        );

        $consulta->execute([
            'username' => trim($username),
        ]);

        $administrador = $consulta->fetch(PDO::FETCH_ASSOC);

        // No permite el acceso si el usuario no existe.
        if ($administrador === false) {
            return null;
        }

        // Compara la contraseña recibida con el hash almacenado.
        if (!password_verify($password, $administrador['password_hash'])) {
            return null;
        }

        // Registra el último acceso únicamente después de autenticar.
        $actualizar = $pdo->prepare(
            'UPDATE admin_users
             SET last_login_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $actualizar->execute([
            'id' => $administrador['id'],
        ]);

        // Devuelve solo los datos que necesita la sesión.
        return [
            'id' => (int) $administrador['id'],
            'username' => $administrador['username'],
            'rol' => (int) $administrador['rol'],
        ];
    }
}
