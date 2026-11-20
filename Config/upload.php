<?php
declare(strict_types=1);

// Moteur uniquement : aucun stockage public ni réception HTTP dans ce lot.
return [
    'input_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    'max_source_bytes' => 10 * 1024 * 1024,
    'max_pixels' => 24_000_000,
    'max_dimension' => 6000,
    'sizes' => ['thumb' => 300, 'medium' => 800, 'large' => 1600],
    'webp_quality' => 82,
    'temporary_root' => sys_get_temp_dir(),
    'memory_budget_bytes' => 448 * 1024 * 1024,
    // Estimation, pas mesure exacte de GD : source RGBA (4), rotation (4),
    // marge décodeur/travail (8) octets par pixel source. Un dérivé et sa
    // relecture de contrôle coexistent (8 octets/pixel dérivé). Traitement sériel.
    'source_bytes_per_pixel' => 16,
    'derivative_bytes_per_pixel' => 8,
    // Réserve supplémentaire pour GD/EXIF/encodeur ; l'usage PHP courant et
    // le poids source sont ajoutés séparément. Ne désactive jamais memory_limit.
    'memory_reserve_bytes' => 24 * 1024 * 1024,
];
