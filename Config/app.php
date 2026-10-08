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
    'contact_email' => $environment('CONTACT_EMAIL', $environment('MAIL_FROM_ADDRESS')),
];
