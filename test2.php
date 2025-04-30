<?php
require __DIR__ . '/vendor/autoload.php';
use Jcupitt\Vips;

// Параметры изображения
$width = 800;  // Замените на X
$height = 600; // Замените на Y

$image
    = Vips\Image::black($width, $height)
    ->add([255, 0, 0])
    ->copy(['interpretation' => 'srgb'])
    ->cast('uchar');

// 2. Генерируем текст с размером
$textImage = Vips\Image::text("{$width}*{$height}", [
    'font' => 'sans 72',
    'width' => $width - 100,
    'align' => 'centre',
    'rgba' => true // Включаем альфа-канал
]);

// 3. Создаем зеленый слой с альфа-каналом из текста
$fontColorMask = $textImage->newFromImage([0, 255, 0])
    ->bandjoin($textImage->extract_band(3)) // Альфа-канал из текста
    ->copy(['interpretation' => 'srgb']);

// 4. Накладываем текст на красное изображение
$result = $image->composite($fontColorMask, 'over', [
    'x' => ($width - $textImage->width) / 2,
    'y' => ($height - $textImage->height) / 2
]);

// 5. Сохраняем результат
$result->writeToFile('output.png');