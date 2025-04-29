<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FakeImageSrc\Common;
use FakeImageSrc\WithGD;

(new DateTime)->setTimezone(new DateTimeZone('Europe/Moscow'));

// require_once __DIR__ . '/functions.php';
$config = require_once __DIR__ . '/config.php';
$cacheFile = '';

# ############################################### #

$request = Common::parseRequest($_SERVER['REQUEST_URI'], $config['defaults']);
$isInternalRequest = Common::isInternalRequest($request['params'], $config);

if ($config['cache']['enabled']) {
    Common::validateCache($config['cache']);

    $cacheKey = md5($request['cacheKey'] . ($isInternalRequest ? '_internal' : ''));
    $cacheFile = Common::getCacheFilePath($config['cache'], $cacheKey, $request['format']);

    if (Common::trySendCachedImage($cacheFile, $request['format'], $config['cache']['expires'])) {
        exit;
    }
}

$imageParams = Common::prepareImageParameters($request, $config['defaults']);
Common::validateParameters($imageParams);

$image = WithGD::generateImage($imageParams);

if ($isInternalRequest) {
    $image = WithGD::addSmartBorder(
        $image,
        $imageParams['width'],
        $imageParams['height'],
        $request['format'],
        $config['defaults']['border_size']
    );
}

if ($config['cache']['enabled']) {
    WithGD::saveToCache($image, $cacheFile, $request['format']);
}

WithGD::sendImage($image, $request['format'], $config['cache']['expires']);
imagedestroy($image);






















