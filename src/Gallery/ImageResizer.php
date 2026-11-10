<?php

declare(strict_types=1);

namespace App\Gallery;

/**
 * Scales photos down with GD: caps the size of uploaded photos and makes
 * thumbnails. Turns phone photos upright (EXIF orientation) on the way.
 * Without the GD extension it does nothing and the original files are used.
 */
class ImageResizer
{
    public function isAvailable(): bool
    {
        return \function_exists('imagecreatetruecolor');
    }

    /**
     * Shrinks the image in place so its long side is at most $max px (and turns
     * it upright). Returns false when the file was left as it was.
     */
    public function limit(string $path, int $max): bool
    {
        $info = $this->info($path);
        if (null === $info) {
            return false;
        }
        [$width, $height, $type] = $info;
        $orientation = $this->orientation($path, $type);
        if (max($width, $height) <= $max && 1 === $orientation) {
            return false;
        }

        $image = $this->load($path, $type, $orientation);
        if (null === $image) {
            return false;
        }

        return $this->save($this->fit($image, $max), $path, $type);
    }

    /**
     * Writes a thumbnail (long side at most $max px) next to $target's extension:
     * WebP when GD supports it, else JPEG. Returns the path written, or null.
     */
    public function thumbnail(string $source, string $target, int $max): ?string
    {
        $info = $this->info($source);
        if (null === $info) {
            return null;
        }
        $image = $this->load($source, $info[2], $this->orientation($source, $info[2]));
        if (null === $image) {
            return null;
        }

        $webp = \function_exists('imagewebp');
        $target = preg_replace('/\.[^.\/]+$/', '', $target).($webp ? '.webp' : '.jpg');
        if (!is_dir(\dirname($target))) {
            mkdir(\dirname($target), 0o775, true);
        }

        return $this->save($this->fit($image, $max), $target, $webp ? \IMAGETYPE_WEBP : \IMAGETYPE_JPEG) ? $target : null;
    }

    /** @return array{int, int, int}|null width, height, IMAGETYPE_* */
    private function info(string $path): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }
        $size = @getimagesize($path);
        if (false === $size || !\in_array($size[2], [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP, \IMAGETYPE_GIF], true)) {
            return null;
        }

        return [$size[0], $size[1], $size[2]];
    }

    private function orientation(string $path, int $type): int
    {
        if (\IMAGETYPE_JPEG !== $type || !\function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($path);

        return \is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
    }

    private function load(string $path, int $type, int $orientation): ?\GdImage
    {
        $image = match ($type) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            \IMAGETYPE_PNG => @imagecreatefrompng($path),
            \IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            \IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };
        if (!$image instanceof \GdImage) {
            return null;
        }

        // EXIF orientations: 3 = 180°, 6 = 90° clockwise, 8 = 90° anticlockwise (mirrored ones: 2, 4, 5, 7).
        if (\in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, \IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };
        if (0 !== $angle) {
            $rotated = imagerotate($image, $angle, 0);
            $image = $rotated instanceof \GdImage ? $rotated : $image;
        }

        return $image;
    }

    private function fit(\GdImage $image, int $max): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if (max($width, $height) <= $max) {
            return $image;
        }
        $scale = $max / max($width, $height);
        $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), \IMG_BICUBIC);

        return $scaled instanceof \GdImage ? $scaled : $image;
    }

    private function save(\GdImage $image, string $path, int $type): bool
    {
        return match ($type) {
            \IMAGETYPE_JPEG => imagejpeg($image, $path, 85),
            \IMAGETYPE_WEBP => imagewebp($image, $path, 80),
            \IMAGETYPE_PNG => imagepng($image, $path, 6),
            \IMAGETYPE_GIF => imagegif($image, $path),
            default => false,
        };
    }
}
