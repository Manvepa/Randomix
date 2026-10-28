<?php

declare(strict_types=1);

namespace Randomix\Controllers;

final class PageAccessController
{
    /**
     * Define las páginas disponibles en Randomix.
     */
    private const ALLOWED_PAGES = [
        'login',
        'dashboard',
        'selection',
        'comparison',
        'merge',
        'admin',
    ];

    /**
     * Define las páginas que requieren autenticación.
     */
    private const PROTECTED_PAGES = [
        'dashboard',
        'selection',
        'comparison',
        'merge',
        'admin',
    ];

    /**
     * Define las páginas reservadas para administradores.
     */
    private const ADMIN_PAGES = [
        'admin',
    ];

    /**
     * Comprueba si la página solicitada existe.
     */
    public function isValidPage(mixed $page): bool
    {
        return is_string($page)
            && in_array($page, self::ALLOWED_PAGES, true);
    }

    /**
     * Comprueba si la página requiere una sesión autenticada.
     */
    public function requiresAuthentication(string $page): bool
    {
        return in_array($page, self::PROTECTED_PAGES, true);
    }

    /**
     * Comprueba si la página está reservada para administradores.
     */
    public function requiresAdministratorRole(string $page): bool
    {
        return in_array($page, self::ADMIN_PAGES, true);
    }

    /**
     * Determina si el usuario puede acceder a la página solicitada.
     */
    public function canAccess(
        string $page,
        bool $isAuthenticated,
        int $role = 0
    ): bool {
        if (!$this->isValidPage($page)) {
            return false;
        }

        if ($this->requiresAuthentication($page) && !$isAuthenticated) {
            return false;
        }

        if (
            $this->requiresAdministratorRole($page)
            && $role !== 1
        ) {
            return false;
        }

        return !($page === 'login' && $isAuthenticated);
    }
}
