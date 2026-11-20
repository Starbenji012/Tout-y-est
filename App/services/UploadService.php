<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;
use ErrorException;
use GdImage;
use InvalidArgumentException;
use Throwable;

/** Moteur local indépendant de HTTP/BDD. Les résultats temporaires appartiennent
 * à l'appelant après succès ; il doit les consommer puis les nettoyer.
 * Le futur contrôleur devra vérifier is_uploaded_file(), Admin et CSRF.
 */
final class UploadService
{
    private readonly array $config;

    public function __construct(?array $configuration = null)
    {
        $defaults = require dirname(__DIR__, 2) . '/Config/upload.php';
        if ($configuration !== null && array_diff_key($configuration, $defaults)) {
            throw new InvalidArgumentException('Configuration images inconnue.');
        }
        $config = array_replace($defaults, $configuration ?? []);
        foreach (['max_source_bytes','max_pixels','max_dimension','memory_budget_bytes',
            'source_bytes_per_pixel','derivative_bytes_per_pixel','memory_reserve_bytes'] as $key) {
            if (!is_int($config[$key]) || $config[$key] < 1) throw new InvalidArgumentException('Limite images invalide.');
        }
        if ($config['max_dimension'] > 32768 || !is_int($config['webp_quality'])
            || $config['webp_quality'] < 0 || $config['webp_quality'] > 100
            || !is_array($config['input_mimes']) || $config['input_mimes'] === []
            || array_diff($config['input_mimes'], ['image/jpeg','image/png','image/webp'])
            || !is_array($config['sizes']) || $config['sizes'] === [] || count($config['sizes']) > 6) {
            throw new InvalidArgumentException('Configuration images invalide.');
        }
        foreach ($config['sizes'] as $name => $side) {
            if (!is_string($name) || !preg_match('/^[a-z]+$/D', $name) || !is_int($side)
                || $side < 1 || $side > $config['max_dimension']) throw new InvalidArgumentException('Taille image invalide.');
        }
        $root = is_string($config['temporary_root']) ? realpath($config['temporary_root']) : false;
        $systemTemporary = realpath(sys_get_temp_dir());
        $repository = realpath(dirname(__DIR__, 2));
        if ($root === false || $systemTemporary === false || !is_dir($root) || !is_writable($root)
            || !self::inside($root, $systemTemporary)
            || ($repository !== false && self::inside($root, $repository))) {
            throw new InvalidArgumentException('Dossier temporaire images non autorisé.');
        }
        $config['temporary_root'] = $root;
        $this->config = $config;
    }

    public function process(string $source): array
    {
        $directory = null;
        $directoryCreated = false;
        $created = [];
        try {
            $this->requireCapabilities();
            $input = $this->inspect($source); // Garde dimensions/mémoire AVANT GD.
            $image = $this->decode($input['path'], $input['mime']);
            if (imagesx($image) !== $input['width'] || imagesy($image) !== $input['height']) {
                throw new DomainException('Les dimensions de cette image sont incohérentes.', 422);
            }
            $orientation = $input['mime'] === 'image/jpeg' ? $this->orientation($input['path']) : 1;
            $image = $this->orient($image, $orientation);
            $width = imagesx($image);
            $height = imagesy($image);
            $directory = $this->config['temporary_root'] . DIRECTORY_SEPARATOR . 'tye-image-' . bin2hex(random_bytes(16));
            if (!$this->checked(static fn() => mkdir($directory, 0700))) throw new DomainException('Création des images impossible pour le moment.', 503);
            $directoryCreated = true;
            $derivatives = [];
            $byDimensions = [];
            foreach ($this->config['sizes'] as $name => $side) {
                [$targetWidth, $targetHeight] = self::dimensions($width, $height, $side);
                $key = $targetWidth . 'x' . $targetHeight;
                // Petite source : les tailles identiques partagent un fichier, sans upscale.
                if (isset($byDimensions[$key])) {
                    $derivatives[$name] = $byDimensions[$key];
                    continue;
                }
                $resized = $this->checked(static fn() => imagecreatetruecolor($targetWidth, $targetHeight));
                if (!$resized instanceof GdImage) throw new DomainException('Redimensionnement impossible.', 422);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                if (!$this->checked(static fn() => imagecopyresampled($resized, $image, 0, 0, 0, 0,
                    $targetWidth, $targetHeight, $width, $height))) throw new DomainException('Redimensionnement impossible.', 422);
                $file = $directory . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.webp';
                // Réservation exclusive du nom ; seul notre fichier est nettoyé en cas d'échec.
                $handle = $this->checked(static fn() => fopen($file, 'x+b'));
                if ($handle === false) throw new DomainException('Création des images impossible pour le moment.', 503);
                fclose($handle);
                $created[] = $file;
                if (!$this->checked(fn() => imagewebp($resized, $file, $this->config['webp_quality']))) {
                    throw new DomainException('Encodage de cette image impossible.', 422);
                }
                unset($resized);
                $metadata = $this->verify($file, $targetWidth, $targetHeight);
                $derivatives[$name] = $byDimensions[$key] = $metadata;
            }
            return ['source' => ['mime' => $input['mime'], 'bytes' => $input['bytes'],
                'width' => $input['width'], 'height' => $input['height'], 'orientation' => $orientation],
                'width' => $width, 'height' => $height, 'derivatives' => $derivatives,
                'temporary_directory' => $directory];
        } catch (Throwable $error) {
            $clean = true;
            foreach ($created as $file) {
                if (is_file($file) && !@unlink($file)) $clean = false;
            }
            if ($directoryCreated && $directory !== null && is_dir($directory) && !@rmdir($directory)) $clean = false;
            if (!$clean) throw new DomainException('Traitement interrompu : le nettoyage temporaire nécessite une intervention.', 503);
            if ($error instanceof DomainException) throw $error;
            // Les messages GD/OS (chemins inclus) ne sont jamais exposés à la future UI.
            throw new DomainException('Cette image ne peut pas être traitée. Vérifiez le fichier ou réessayez.', 422);
        } finally {
            unset($image, $resized);
        }
    }

    private function requireCapabilities(): void
    {
        foreach (['imagecreatefromjpeg','imagecreatefrompng','imagecreatefromwebp','imagewebp',
            'imagecopyresampled','exif_read_data','imagerotate','imageflip'] as $function) {
            if (!function_exists($function)) throw new DomainException('Le traitement des images est indisponible sur ce serveur.', 503);
        }
        if (!class_exists(\finfo::class)) throw new DomainException('Le traitement des images est indisponible sur ce serveur.', 503);
    }

    private function inspect(string $source): array
    {
        if ($source === '' || str_contains($source, "\0") || str_contains($source, '://')) {
            throw new DomainException('Choisissez un fichier image local valide.', 422);
        }
        $path = realpath($source);
        if ($path === false || !is_file($path) || !is_readable($path)) throw new DomainException('Le fichier image est introuvable ou illisible.', 422);
        clearstatcache(true, $path);
        $bytes = filesize($path);
        if ($bytes === false || $bytes < 1) throw new DomainException('Ce fichier image est vide ou illisible.', 422);
        if ($bytes > $this->config['max_source_bytes']) throw new DomainException('Cette image dépasse la taille de fichier autorisée.', 422);
        $mime = $this->checked(static fn() => (new \finfo(FILEINFO_MIME_TYPE))->file($path));
        if (!in_array($mime, $this->config['input_mimes'], true)) throw new DomainException('Choisissez une image JPEG, PNG ou WebP.', 422);
        $size = $this->checked(static fn() => getimagesize($path));
        if ($size === false || $size[0] < 1 || $size[1] < 1 || ($size['mime'] ?? null) !== $mime) {
            throw new DomainException('Le contenu de cette image est invalide ou endommagé.', 422);
        }
        [$width, $height] = $size;
        if ($width > $this->config['max_dimension'] || $height > $this->config['max_dimension']
            || $width * $height > $this->config['max_pixels']) {
            throw new DomainException('Cette image contient trop de pixels ou des dimensions trop grandes. Réduisez ses dimensions.', 422);
        }
        [$dw, $dh] = self::dimensions($width, $height, max($this->config['sizes']));
        $needed = $width * $height * $this->config['source_bytes_per_pixel']
            + $dw * $dh * $this->config['derivative_bytes_per_pixel']
            + $this->config['memory_reserve_bytes'] + $bytes;
        $limit = $this->config['memory_budget_bytes'];
        $phpLimit = self::memoryBytes((string) ini_get('memory_limit'));
        if ($phpLimit > 0) $limit = min($limit, $phpLimit);
        if ($needed > $limit - memory_get_usage(true)) {
            throw new DomainException('Cette image est trop grande pour être traitée en sécurité. Réduisez ses dimensions.', 422);
        }
        return compact('path', 'mime', 'bytes', 'width', 'height');
    }

    private function decode(string $path, string $mime): GdImage
    {
        // Le php.ini peut masquer les avertissements libjpeg (JPEG tronqués).
        // Mode strict pendant ce seul décodage, puis restauration de la valeur courante.
        $jpegWarnings = $mime === 'image/jpeg' ? ini_get('gd.jpeg_ignore_warning') : false;
        if ($mime === 'image/jpeg' && ini_set('gd.jpeg_ignore_warning', '0') === false) {
            throw new DomainException('La validation des images JPEG est indisponible.', 503);
        }
        try {
            $image = $this->checked(static fn() => match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($path),
                'image/png' => imagecreatefrompng($path),
                'image/webp' => imagecreatefromwebp($path),
            });
            if (!$image instanceof GdImage) throw new DomainException('Le contenu de cette image est invalide ou endommagé.', 422);
            return $image;
        } finally {
            if ($jpegWarnings !== false) ini_set('gd.jpeg_ignore_warning', $jpegWarnings);
        }
    }

    private function orientation(string $path): int
    {
        // Un JPEG sans EXIF est valide ; EXIF peut alors retourner false avec un avertissement.
        $exif = @exif_read_data($path, 'IFD0', true, false);
        $orientation = $exif['IFD0']['Orientation'] ?? 1;
        if (!is_int($orientation) || $orientation < 1 || $orientation > 8) throw new DomainException('L’orientation de cette photo est invalide.', 422);
        return $orientation;
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        $angle = match ($orientation) {3 => 180, 5, 6, 7 => -90, 8 => 90, default => 0};
        if ($angle !== 0) {
            $rotated = $this->checked(static fn() => imagerotate($image, $angle, 0));
            if (!$rotated instanceof GdImage) throw new DomainException('Orientation de cette image impossible.', 422);
            $image = $rotated;
        }
        $flip = match ($orientation) {2, 5 => IMG_FLIP_HORIZONTAL, 4, 7 => IMG_FLIP_VERTICAL, default => null};
        if ($flip !== null && !$this->checked(static fn() => imageflip($image, $flip))) throw new DomainException('Orientation de cette image impossible.', 422);
        return $image;
    }

    private function verify(string $file, int $width, int $height): array
    {
        clearstatcache(true, $file);
        $bytes = is_file($file) ? filesize($file) : false;
        if ($bytes === false || $bytes < 1) throw new DomainException('La création de cette image a échoué.', 422);
        $mime = $this->checked(static fn() => (new \finfo(FILEINFO_MIME_TYPE))->file($file));
        $size = $this->checked(static fn() => getimagesize($file));
        if ($mime !== 'image/webp' || $size === false || $size[0] !== $width || $size[1] !== $height) throw new DomainException('La vérification de cette image a échoué.', 422);
        $verified = $this->decode($file, 'image/webp');
        if (imagesx($verified) !== $width || imagesy($verified) !== $height) throw new DomainException('La vérification de cette image a échoué.', 422);
        return ['path' => $file, 'mime' => 'image/webp', 'bytes' => $bytes, 'width' => $width, 'height' => $height];
    }

    private function checked(callable $operation): mixed
    {
        set_error_handler(static function(int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {return $operation();} finally {restore_error_handler();}
    }

    private static function dimensions(int $width, int $height, int $side): array
    {
        $ratio = min(1, $side / max($width, $height));
        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }

    private static function memoryBytes(string $value): int
    {
        if ($value === '-1') return -1;
        if (!preg_match('/^(\d+)\s*([KMG]?)$/iD', trim($value), $match)) return 0;
        return (int) $match[1] * match (strtoupper($match[2])) {'K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3, default => 1};
    }

    private static function inside(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (DIRECTORY_SEPARATOR === '\\') {$path = strtolower($path); $root = strtolower($root);}
        return $path === $root || str_starts_with($path, $root . '/');
    }
}
