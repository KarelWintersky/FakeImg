<?php

/**
 * @param $defaults
 * @return string
 */
function getCacheKey($defaults): string
{
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);

    // Нормализуем параметры для единообразия
    parse_str($query, $params);
    if (!isset($params['text']) && $defaults['default_text'] === null) {
        unset($params['text']); // Удаляем text если он не указан и по умолчанию null
    }

    return md5($path . serialize($params));
}


/**
 *
 */
#[NoReturn]
function sendCachedImage($cacheFile, $format):void {
    $mimeTypes = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp'
    ];

    header('Content-Type: ' . $mimeTypes[$format]);
    header('Content-Length: ' . filesize($cacheFile));
    header('Cache-Control: public, max-age=' . ($GLOBALS['cacheConfig']['expires'] / 1));
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $GLOBALS['cacheConfig']['expires']) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($cacheFile)) . ' GMT');

    readfile($cacheFile);
    exit;
}

// Функция проверки hex-цвета
function isValidHexColor($color) {
    return preg_match('/^[0-9a-f]{3,6}$/i', $color);
}

// Функция преобразования hex в rgb
function hex2rgb($hex) {
    // Упрощаем 3-значные цвета до 6-значных
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return [$r, $g, $b];
}




