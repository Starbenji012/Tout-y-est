<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/** Offre aux contrôleurs les outils communs de rendu des vues. */
abstract class Controller
{
    private static ?\App\Services\AdminAccessService $adminAccess = null;

    /** Dépendance de requête partagée par les vues publiques, sans rôle issu du navigateur. */
    public static function configureAdminAccess(\App\Services\AdminAccessService $access): void
    {
        self::$adminAccess = $access;
    }
    /** Rend une vue dans un layout après avoir rendu son contenu en mémoire. */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewsDirectory = dirname(__DIR__) . '/views';
        $viewPath = $viewsDirectory . '/' . $view . '.php';
        $layoutPath = $viewsDirectory . '/layouts/' . $layout . '.php';

        if (!is_file($viewPath) || !is_file($layoutPath)) {
            throw new RuntimeException('Vue introuvable.');
        }

        $showAdminBridge = false;
        if ($layout === 'main' && self::$adminAccess !== null) {
            $user = Session::get('user');
            if (is_array($user) && (int)($user['id'] ?? 0)>0) {
                try {$showAdminBridge = !isset(self::$adminAccess->accessForUser((int)$user['id'])['error']);}
                catch (Throwable) {$showAdminBridge = false;}
            }
        }
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $viewPath;
            $content = (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        require $layoutPath;
    }

    /** Rend un composant isolé et retourne son HTML sous forme de chaîne. */
    protected function renderPartial(string $view, array $data = []): string
    {
        $viewPath = dirname(__DIR__) . '/views/' . $view . '.php';

        if (!is_file($viewPath)) {
            throw new RuntimeException('Vue partielle introuvable.');
        }

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $viewPath;

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
