<?php

namespace FakeImageSrc;

interface ImageProcessorInterface
{
    /**
     * Генерация изображения
     *
     * @param array $imageParams
     * @return mixed
     */
    public function generateImage(array $imageParams);

    /**
     * Генерация "хитрой" рамки
     *
     * @param $image
     * @param int $width
     * @param int $height
     * @param string $format
     * @param int $borderSize
     * @return mixed
     */
    public function addSmartBorder($image, int $width, int $height, string $format, int $borderSize = 1): mixed;

    /**
     * Сохранение изображения в кэш
     *
     * @param $image
     * @param string $cacheFile
     * @param string $format
     * @return void
     */
    public function saveToCache($image, string $cacheFile, string $format): void;

    /**
     * Отсылка изображения в браузер
     *
     * @param $image
     * @param string $format
     * @param int $expires
     * @return void
     */
    public function sendImage(mixed $image, string $format, int $expires): void;

    public function imageDestroy($image);
}