<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CategoryService;

/** Expose les données de navigation des catégories au format JSON. */
final class CategoryController
{
    /** Injecte le service responsable de préparer la navigation. */
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly Request $request,
    ) {
    }

    /** Retourne la hiérarchie et les premières mises en avant du méga menu. */
    public function navigation(): void
    {
        Response::json($this->categoryService->navigation());
    }

    /** Retourne les produits mis en avant pour la catégorie demandée. */
    public function highlights(): void
    {
        $category = $this->request->queryParameters()['category'] ?? null;

        Response::json([
            'highlights' => $this->categoryService->highlights(is_string($category) ? $category : null),
        ]);
    }
}
