<?php

namespace FakeImageSrc;

use InvalidArgumentException;

class ImageProcessorFactory
{
    /**
     * Choose processor
     *
     * @param string $type
     * @return ImageProcessorInterface
     */
    public static function create(string $type): ImageProcessorInterface {
        return match ($type) {
            'gd' => new ImageProcessor_GD(),
            'vips' => new ImageProcessor_Vips(),
            default => throw new InvalidArgumentException('Unknown processor type'),
        };
    }

}

/*
// В index.php
$processor = ImageProcessorFactory::create($config['image_processor']);
$processor->method1();

 */