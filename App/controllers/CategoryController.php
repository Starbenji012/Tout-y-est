<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\CategoryService;

final class CategoryController
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    public function navigation(): void
    {
        Response::json($this->categoryService->navigation());
    }
}
