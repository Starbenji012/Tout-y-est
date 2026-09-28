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
    'app_url' => rtrim($environment('APP_URL', 'http://localhost:8000'), '/'),
    'transport' => strtolower($environment('MAIL_TRANSPORT')),
    'host' => $environment('MAIL_HOST'),
    'port' => (int) $environment('MAIL_PORT', '587'),
    'username' => $environment('MAIL_USERNAME'),
    'password' => $environment('MAIL_PASSWORD'),
    'encryption' => strtolower($environment('MAIL_ENCRYPTION', 'tls')),
    'from_address' => $environment('MAIL_FROM_ADDRESS'),
    'from_name' => $environment('MAIL_FROM_NAME', 'Tout y est'),
];
