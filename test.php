<?php

require 'vendor/autoload.php';
use Jcupitt\Vips;

try {
    // Создаем массив с данными изображения (значения от 0 до 255)
    $pixels = [
        [255, 0, 0],    // красный
        [0, 255, 0],    // зеленый
        [0, 0, 255],    // синий
        [255, 255, 0],  // желтый
    ];

    $image = Vips\Image::black(300, 100)->add([255, 0, 0])->cast('uchar');

    // $image = $pixel->embed(0, 0, 300, 100, [ 'extend' => 'copy']);

    // Сохраняем изображение
    $image->writeToFile('output.png');

    echo "Изображение успешно создано!";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}