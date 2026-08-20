<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function queryParameters(): array
    {
        return $_GET;
    }

    public function queryInteger(string $key): int
    {
        $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);

        return $value === false ? 0 : (int) $value;
    }
}
