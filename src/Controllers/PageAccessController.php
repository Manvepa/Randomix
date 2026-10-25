<?php

declare(strict_types=1);

namespace Randomix\Controllers;

final class PageAccessController
{
    /**
     * Define las páginas disponibles y las que requieren autenticación.
     */
    private const ALLOWED_PAGES = [
        'login',
        'dashboard',
        'selection',
        'comparison',
        'merge',
    ];

    private const PROTECTED_PAGES = [
        'dashboard',
        'selection',
        'comparison',
        'merge',
    ];

    /**
     * Valida el nombre de la página solicitada.
     */
    public function isValidPage(mixed $page): bool
    {
        return is_string($page)
            && in_array($page, self::ALLOWED_PAGES, true);
    }

    /**
     * Comprueba si una página requiere una sesión autenticada.
     */
    public function requiresAuthentication(string $page): bool
    {
        return in_array($page, self::PROTECTED_PAGES, true);
    }

    /**
     * Determina si el usuario puede acceder a la página solicitada.
     */
    public function canAccess(string $page, bool $isAuthenticated): bool
    {
        if (!$this->isValidPage($page)) {
            return false;
        }

        if ($this->requiresAuthentication($page) && !$isAuthenticated) {
            return false;
        }

        return !($page === 'login' && $isAuthenticated);
    }
}
