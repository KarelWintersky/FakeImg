<?php

(new DateTime)->setTimezone(new DateTimeZone('Europe/Moscow'));

require_once __DIR__ . '/_functions.php';
$config = require_once __DIR__ . '/_config.php';
$cacheFile = '';

# ############################################### #

$request = parseRequest($_SERVER['REQUEST_URI'], $config['defaults']);
$isInternalRequest = checkReferer($_SERVER['HTTP_REFERER'] ?? '', $config['defaults']['protected_domains']);

if ($config['cache']['enabled']) {
    validateCache($config['cache']);

    $cacheKey = md5($request['cacheKey'] . ($isInternalRequest ? '_internal' : ''));
    $cacheFile = getCacheFilePath($config['cache'], $cacheKey, $request['format']);

    if (trySendCachedImage($cacheFile, $request['format'], $config['cache']['expires'])) {
        exit;
    }
}

$imageParams = prepareImageParameters($request, $config['defaults']);
validateParameters($imageParams);

$image = generateImage($imageParams);

if ($isInternalRequest) {
    $image = addSmartBorder(
        $image,
        $imageParams['width'],
        $imageParams['height'],
        $request['format'],
        $config['defaults']['border_size']
    );
}

if ($config['cache']['enabled']) {
    saveToCache($image, $cacheFile, $request['format']);
}

sendImage($image, $request['format'], $config['cache']['expires']);
imagedestroy($image);






















