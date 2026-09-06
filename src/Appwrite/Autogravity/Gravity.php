<?php

namespace Appwrite\Autogravity;

use Utopia\Image\Image;

class Gravity
{
    public function __construct(
        public readonly float $x,
        public readonly float $y
    ) {
        if ($x < 0 || $x > 1 || $y < 0 || $y > 1) {
            throw new Exception('Autogravity returned an invalid gravity');
        }
    }

    public function getType(int $width, int $height, int $sourceWidth, int $sourceHeight): string
    {
        $sourceAspect = $sourceWidth / $sourceHeight;
        $targetAspect = $width / $height;
        if ($targetAspect > $sourceAspect) {
            $resizeWidth = $width;
            $resizeHeight = (int) \ceil($width / $sourceAspect);
        } else {
            $resizeWidth = (int) \ceil($height * $sourceAspect);
            $resizeHeight = $height;
        }

        $horizontal = match (true) {
            $this->x * $resizeWidth < ($resizeWidth - $width) / 2 => 'left',
            $this->x * $resizeWidth > ($resizeWidth + $width) / 2 => 'right',
            default => '',
        };
        $vertical = match (true) {
            $this->y * $resizeHeight < ($resizeHeight - $height) / 2 => 'top',
            $this->y * $resizeHeight > ($resizeHeight + $height) / 2 => 'bottom',
            default => '',
        };

        return match ([$vertical, $horizontal]) {
            ['', ''] => Image::GRAVITY_CENTER,
            ['', 'left'] => Image::GRAVITY_LEFT,
            ['', 'right'] => Image::GRAVITY_RIGHT,
            ['top', ''] => Image::GRAVITY_TOP,
            ['top', 'left'] => Image::GRAVITY_TOP_LEFT,
            ['top', 'right'] => Image::GRAVITY_TOP_RIGHT,
            ['bottom', ''] => Image::GRAVITY_BOTTOM,
            ['bottom', 'left'] => Image::GRAVITY_BOTTOM_LEFT,
            ['bottom', 'right'] => Image::GRAVITY_BOTTOM_RIGHT,
        };
    }

    public function unrotate(int $rotation): self
    {
        return match (($rotation % 360 + 360) % 360) {
            0 => $this,
            90 => new self($this->y, 1 - $this->x),
            180 => new self(1 - $this->x, 1 - $this->y),
            270 => new self(1 - $this->y, $this->x),
            default => throw new Exception('Autogravity cannot reverse an unsupported rotation'),
        };
    }

    /**
     * @return array{x: float, y: float}
     */
    public function getArrayCopy(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }
}
