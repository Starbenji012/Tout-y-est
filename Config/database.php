<?php

declare(strict_types=1);

$localEnvironment = [];
$environmentFile = dirname(__DIR__) . '/.env';

if (is_file($environmentFile)) {
    $parsedEnvironment = parse_ini_file($environmentFile, false, INI_SCANNER_RAW);
    $localEnvironment = is_array($parsedEnvironment) ? $parsedEnvironment : [];
}

$environment = static function (string $name, string $default = '') use ($localEnvironment): string {
    $systemValue = getenv($name);

    if (is_string($systemValue) && $systemValue !== '') {
        return $systemValue;
    }

    $localValue = $localEnvironment[$name] ?? null;

    return is_string($localValue) && $localValue !== '' ? $localValue : $default;
};

return [
    'driver' => 'mysql',
    'host' => $environment('DB_HOST', '127.0.0.1'),
    'port' => (int) $environment('DB_PORT', '3306'),
    'database' => $environment('DB_DATABASE', 'tout_y_est'),
    'username' => $environment('DB_USERNAME', 'root'),
    'password' => $environment('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'options' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_TIMEOUT => 2,
    ],
];
