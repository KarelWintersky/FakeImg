<?php

(new DateTime)->setTimezone(new DateTimeZone('Europe/Moscow'));

require_once __DIR__ . '/_functions.php';

// Конфигурация
$config = [
    'cache' => [
        'enabled' => false,
        'directory' => __DIR__ . '/../cache',
        'expires' => 1,
        'gc_probability' => 10,
    ],
    'defaults' => [
        'font_size' => 30,
        'bg_color' => '3d4070',
        'text_color' => 'ffffff',
        'default_size' => 150,
        'default_text' => null,
        'default_format' => 'png',
        'min_font_size' => 8,
        'max_font_size' => 100,
        'font_ratio' => 0.15,
        'font' => __DIR__ . '/fonts/segoe-ui.ttf',
        'max_dimension' => 2000,
    ]
];

$request = parseRequest($_SERVER['REQUEST_URI'], $config['defaults']);

if ($config['cache']['enabled']) {
    validateCache($config['cache']);

    $cacheFile = getCacheFilePath($config['cache'], $request['cacheKey'], $request['format']);

    if (trySendCachedImage($cacheFile, $request['format'], $config['cache']['expires'])) {
        exit;
    }
}

$imageParams = prepareImageParameters($request, $config['defaults']);
validateParameters($imageParams);

$image = generateImage($imageParams);

if ($config['cache']['enabled']) {
    saveToCache($image, $cacheFile, $request['format']);
}

sendImage($image, $request['format'], $config['cache']['expires']);
imagedestroy($image);






















