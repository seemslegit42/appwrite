<?php

declare(strict_types=1);

namespace Tests\Unit\Autogravity;

use Appwrite\Autogravity\Exception;
use Appwrite\Autogravity\Gravity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utopia\Image\Image;

final class GravityTest extends TestCase
{
    /**
     * @return array<string, array{float, float, int, int, int, int, string}>
     */
    public static function typeProvider(): array
    {
        return [
            'landscape to portrait, subject right' => [0.7, 0.5, 180, 320, 1280, 720, Image::GRAVITY_RIGHT],
            'landscape to portrait, subject left' => [0.2, 0.5, 180, 320, 1280, 720, Image::GRAVITY_LEFT],
            'landscape to wide, subject remains visible' => [0.7, 0.5, 800, 600, 1280, 720, Image::GRAVITY_CENTER],
            'portrait to landscape, subject top' => [0.5, 0.1, 600, 300, 600, 900, Image::GRAVITY_TOP],
            'portrait to landscape, subject bottom' => [0.9, 0.9, 600, 300, 600, 900, Image::GRAVITY_BOTTOM],
        ];
    }

    #[DataProvider('typeProvider')]
    public function testGetType(float $x, float $y, int $width, int $height, int $sourceWidth, int $sourceHeight, string $expected): void
    {
        $this->assertSame($expected, (new Gravity($x, $y))->getType($width, $height, $sourceWidth, $sourceHeight));
    }

    public function testRejectsInvalidCoordinates(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Autogravity returned an invalid gravity');

        new Gravity(1.1, 0.5);
    }

    public function testGetArrayCopy(): void
    {
        $this->assertSame(['x' => 0.2, 'y' => 0.8], (new Gravity(0.2, 0.8))->getArrayCopy());
    }
}
