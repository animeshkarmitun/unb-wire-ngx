<?php

namespace App\Support;

/**
 * COS-6: encodes raw image bytes (JPEG/PNG/WebP) into WebP format via GD,
 * preserving alpha transparency and optionally constraining maximum width
 * without upscaling smaller sources.
 */
class ImageWebpConverter
{
    /**
     * @return array{bytes: string, width: int, height: int, size_bytes: int}|null
     */
    public static function convertBytes(string $bytes, ?int $maxWidth = null, int $quality = 85): ?array
    {
        if ($bytes === '' || ! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return null;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($src);

            return null;
        }

        $scale = ($maxWidth !== null && $maxWidth > 0) ? min(1, $maxWidth / $width) : 1;
        $tw = max(1, (int) round($width * $scale));
        $th = max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $tw, $th, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $width, $height);
        imagedestroy($src);

        ob_start();
        $ok = imagewebp($dst, null, $quality);
        $data = (string) ob_get_clean();
        imagedestroy($dst);

        if (! $ok || $data === '') {
            return null;
        }

        return [
            'bytes' => $data,
            'width' => $tw,
            'height' => $th,
            'size_bytes' => strlen($data),
        ];
    }
}
