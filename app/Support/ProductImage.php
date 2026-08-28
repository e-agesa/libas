<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a product photograph, shrinking it first.
 *
 * Staff photograph stock on a phone, so the files arrive at 4–12 MB and several
 * thousand pixels wide. Rejecting those (the old 2 MB rule) stopped stock entry
 * dead. Nothing on the site displays a picture larger than about 1600px, so the
 * photo is scaled down and re-encoded on the way in: uploads succeed, the
 * catalogue stays fast, and the disk does not fill up with camera originals.
 *
 * Uses GD, which this server has. If anything about the resize fails, the
 * original file is stored unchanged rather than losing the upload.
 */
class ProductImage
{
    /** Longest edge kept, in pixels. */
    public const MAX_EDGE = 1600;

    /** JPEG quality for the re-encoded file. */
    public const QUALITY = 82;

    /**
     * @return string the stored path, relative to the public disk
     */
    public static function store(UploadedFile $file, string $directory = 'collections'): string
    {
        $original = $file->store($directory, 'public');

        if (!extension_loaded('gd')) {
            return $original;
        }

        $absolute = Storage::disk('public')->path($original);

        try {
            $resized = self::shrink($absolute);
        } catch (\Throwable $e) {
            // A photo we cannot process is still a photo worth keeping.
            return $original;
        }

        return $original;
    }

    /**
     * Scale the file in place if it is larger than MAX_EDGE. Returns true when
     * the file was rewritten.
     */
    protected static function shrink(string $path): bool
    {
        $info = @getimagesize($path);
        if (!$info) {
            return false;
        }

        [$width, $height] = $info;
        $mime = $info['mime'] ?? '';

        if ($width <= self::MAX_EDGE && $height <= self::MAX_EDGE) {
            return false; // already a sensible size
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif'  => @imagecreatefromgif($path),
            default      => null,
        };

        if (!$source) {
            return false;
        }

        $scale = self::MAX_EDGE / max($width, $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);

        // Keep transparency for the formats that have it.
        if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $written = match ($mime) {
            'image/jpeg' => imagejpeg($target, $path, self::QUALITY),
            'image/png'  => imagepng($target, $path, 6),
            'image/webp' => imagewebp($target, $path, self::QUALITY),
            'image/gif'  => imagegif($target, $path),
            default      => false,
        };

        imagedestroy($source);
        imagedestroy($target);

        return (bool) $written;
    }
}
