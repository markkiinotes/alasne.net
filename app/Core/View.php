<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], string $layout = 'main'): string
    {
        $viewPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        $layoutPath = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';

        if (!file_exists($viewPath)) {
            return "View not found: {$view}";
        }

        extract($data);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if (!file_exists($layoutPath)) {
            return $content;
        }

        ob_start();
        require $layoutPath;

        return ob_get_clean();
    }
}