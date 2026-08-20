<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewsDirectory = dirname(__DIR__) . '/views';
        $viewPath = $viewsDirectory . '/' . $view . '.php';
        $layoutPath = $viewsDirectory . '/layouts/' . $layout . '.php';

        if (!is_file($viewPath) || !is_file($layoutPath)) {
            throw new RuntimeException('Vue introuvable.');
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
