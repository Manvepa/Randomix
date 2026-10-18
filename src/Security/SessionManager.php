<?php

declare(strict_types=1);

namespace Randomix\Security;

use RuntimeException;

final class SessionManager
{
    /**
     * Configura e inicia la sesión de Randomix.
     *
     * La configuración se aplica antes de session_start(), porque
     * los parámetros de las cookies deben establecerse antes de enviar
     * las cabeceras HTTP.
     */
    public static function start(): void
    {
        // Evita intentar iniciar una sesión que ya está activa.
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Las cookies deben estar habilitadas para gestionar la sesión.
        if (headers_sent()) {
            throw new RuntimeException(
                'No se puede iniciar la sesión porque ya se enviaron cabeceras HTTP.'
            );
        }

        // Detecta HTTPS sin confiar en cabeceras enviadas por el cliente.
        $httpsActivo = isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && $_SERVER['HTTPS'] !== 'off';

        // Usa un nombre propio para distinguir la sesión de Randomix.
        session_name('RANDOMIXSESSID');

        // Refuerza la gestión de sesiones antes de iniciarla.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        // Configura las propiedades de seguridad de la cookie de sesión.
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $httpsActivo,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Crea o recupera la sesión mediante la cookie del navegador.
        if (!session_start()) {
            throw new RuntimeException(
                'No fue posible iniciar la sesión de Randomix.'
            );
        }
    }

    /**
     * Renueva el identificador de sesión después de autenticar al usuario.
     *
     * Esto reduce el riesgo de que alguien reutilice un identificador
     * de sesión conocido antes del inicio de sesión.
     */
    public static function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'No se puede renovar una sesión que no está activa.'
            );
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'No fue posible renovar el identificador de sesión.'
            );
        }
    }
}
