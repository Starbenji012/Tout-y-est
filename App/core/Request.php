<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function queryParameters(): array
    {
        return $_GET;
    }

    public function postParameters(): array
    {
        return $_POST;
    }

    public function queryInteger(string $key): int
    {
        $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);

        return $value === false ? 0 : (int) $value;
    }
}
