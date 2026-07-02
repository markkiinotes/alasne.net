<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public function status(int $code): self
    {
        http_response_code($code);

        return $this;
    }

    public function json(array $data): never
    {
        header('Content-Type: application/json');

        echo json_encode($data, JSON_PRETTY_PRINT);

        exit;
    }

    public function redirect(string $url): never
    {
        header("Location: {$url}");

        exit;
    }

    public function view(string $content): never
    {
        echo $content;

        exit;
    }
}