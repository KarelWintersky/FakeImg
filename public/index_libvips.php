<?php

require __DIR__ . '/vendor/autoload.php';

use FakeImageSrc\Common;
use FakeImageSrc\WithVips;

// Конфигурация
$config = [
    'cache' => [
        'enabled' => true,
        'directory' => __DIR__ . '/cache',
        'expires' => 86400, // 1 день
    ],
    'defaults' => [
        'width' => 300,
        'height' => 200,
        'bg_color' => '3d4070',
        'text_color' => 'ffffff',
        'font_size' => 30,
        'font_file' => __DIR__ . '/fonts/Roboto-Regular.ttf',
        'min_font_size' => 8,
        'max_font_size' => 100,
        'font_ratio' => 0.15,
    ]
];

// Обработка запроса
$request = Common::parseRequest($_SERVER['REQUEST_URI'], $config['defaults']);
$image = WithVips::generateImage($request, $config);
WithVips::sendImage($image, $request['format']);






