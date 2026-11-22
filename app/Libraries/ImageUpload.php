<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pictures uploaded by staff (product / variant pictures): validated by their real content with getimagesize()
 * (works without the fileinfo/GD extensions), stored outside public/ under writable/uploads/<folder>/ with a random
 * name, and streamed by a controller.
 */
final class ImageUpload
{
    /** Image type => safe extension. */
    private const TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Whether the request contains a file for $field.
     *
     * @param UploadedFile|null $file Uploaded file (getFile()).
     *
     * @return bool
     */
    public static function present(?UploadedFile $file): bool
    {
        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Validate an uploaded picture.
     *
     * @param UploadedFile $file Uploaded file.
     *
     * @return string|null Error message, or null when valid.
     */
    public static function check(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return lang('Products.imageUploadFailed', [$file->getErrorString()]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return lang('Products.imageTooLarge', ['2 MB']);
        }

        $info = @getimagesize($file->getTempName());

        if ($info === false || ! isset(self::TYPES[$info[2]])) {
            return lang('Products.imageType');
        }

        return null;
    }

    /**
     * Store a validated picture under writable/uploads/<folder>/ with a random name.
     *
     * @param UploadedFile $file   Validated file (see check()).
     * @param string       $folder Sub folder, e.g. 'products'.
     *
     * @return string Path relative to writable/uploads/.
     */
    public static function store(UploadedFile $file, string $folder): string
    {
        $info = getimagesize($file->getTempName());
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$info[2]];
        $file->move(WRITEPATH . 'uploads/' . $folder, $name, true);

        return $folder . '/' . $name;
    }

    /**
     * Delete a stored picture (ignored when missing or outside the folder).
     *
     * @param string|null $path   Path relative to writable/uploads/.
     * @param string      $folder Expected sub folder.
     *
     * @return void
     */
    public static function remove(?string $path, string $folder): void
    {
        $file = $path ? realpath(WRITEPATH . 'uploads/' . $path) : false;

        if ($file !== false && str_starts_with($file, (string) realpath(WRITEPATH . 'uploads/' . $folder)) && is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Stream a stored picture.
     *
     * @param ResponseInterface $response Response to fill.
     * @param string|null       $path     Path relative to writable/uploads/.
     * @param string            $folder   Expected sub folder (files elsewhere are refused).
     *
     * @return ResponseInterface
     *
     * @throws PageNotFoundException When the picture is missing.
     */
    public static function stream(ResponseInterface $response, ?string $path, string $folder): ResponseInterface
    {
        $file = $path ? realpath(WRITEPATH . 'uploads/' . $path) : false;

        if ($file === false || ! str_starts_with($file, (string) realpath(WRITEPATH . 'uploads/' . $folder)) || ! is_file($file)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $info = @getimagesize($file);

        return $response
            ->setHeader('Content-Type', $info['mime'] ?? 'application/octet-stream')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($file));
    }
}
