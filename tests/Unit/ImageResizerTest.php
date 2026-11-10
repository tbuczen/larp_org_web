<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Gallery\ImageResizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ImageResizerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD is not installed.');
        }
        $this->dir = sys_get_temp_dir().'/resizer-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testLimitShrinksOnlyLargeImages(): void
    {
        $resizer = new ImageResizer();
        $big = $this->jpeg('big.jpg', 3000, 1500);
        $small = $this->jpeg('small.jpg', 800, 600);
        $before = (string) file_get_contents($small);

        self::assertTrue($resizer->limit($big, 1200));
        self::assertSame([1200, 600], \array_slice((array) getimagesize($big), 0, 2));
        self::assertFalse($resizer->limit($small, 1200));
        self::assertSame($before, file_get_contents($small), 'small files are left untouched');
    }

    public function testThumbnailKeepsTheShapeAndUsesWebpWhenPossible(): void
    {
        $source = $this->jpeg('photo.jpg', 1000, 1500);
        $thumb = (new ImageResizer())->thumbnail($source, $this->dir.'/thumbs/photo.jpg', 300);

        self::assertNotNull($thumb);
        self::assertSame(\function_exists('imagewebp') ? 'webp' : 'jpg', pathinfo($thumb, \PATHINFO_EXTENSION));
        self::assertSame([200, 300], \array_slice((array) getimagesize($thumb), 0, 2));
    }

    public function testNonImagesAreIgnored(): void
    {
        file_put_contents($this->dir.'/notes.jpg', 'not an image');

        self::assertFalse((new ImageResizer())->limit($this->dir.'/notes.jpg', 100));
        self::assertNull((new ImageResizer())->thumbnail($this->dir.'/notes.jpg', $this->dir.'/t.jpg', 100));
    }

    private function jpeg(string $name, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $this->dir.'/'.$name);

        return $this->dir.'/'.$name;
    }
}
