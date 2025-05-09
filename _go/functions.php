<?php

/**
 * Проверяет, является ли запрос внутренним (с одного из защищенных доменов)
 *
 * @param string $referer
 * @param array $allowedDomains
 * @return bool
 */
function checkReferer(string $referer, array $allowedDomains): bool
{
    if (empty($referer)) {
        return false;
    }

    $refererHost = parse_url($referer, PHP_URL_HOST);

    foreach ($allowedDomains as $domain) {
        if (str_contains($refererHost, $domain)) {
            return true;
        }
    }

    return false;
}

/**
 * Проверяет, является ли запрос внутренним по параметру internal=1
 *
 * @param array $params
 * @param array $config
 * @return bool
 */
function isInternalRequest(array $params, array $config): bool
{
    if (isset($params['internal'])) {
        return $params['internal'] === '1';
    }
    return false;

    // Дополнительная проверка по REFERER (если нужно)
    /*$referer = $_SERVER['HTTP_REFERER'] ?? '';
    return checkReferer($referer, $config['defaults']['allowed_domains']);*/
}


/**
 * Разбирает URL запроса
 */
function parseRequest(string $requestUri, array $defaults): array
{
    $path = parse_url($requestUri, PHP_URL_PATH);
    $query = parse_url($requestUri, PHP_URL_QUERY);
    parse_str($query, $params);

    $parts = explode('/', trim($path, '/'));
    $lastPart = end($parts);

    $format = pathinfo($lastPart, PATHINFO_EXTENSION);
    if (!in_array($format, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
        $format = $defaults['default_format'];
    }

    $parts[count($parts)-1] = pathinfo($lastPart, PATHINFO_FILENAME);

    return [
        'parts' => $parts,
        'params' => $params,
        'format' => $format,
        'cacheKey' => md5($requestUri . serialize($params)),
    ];
}

/**
 * Подготавливает параметры изображения
 */
function prepareImageParameters(array $request, array $defaults): array
{
    $parts = $request['parts'];
    $params = $request['params'];

    // Определение параметров из URL
    if (count($parts) >= 4 && is_numeric($parts[0])) {
        [$fontSize, $bgColor, $textColor, $dimensions] = array_slice($parts, 0, 4);
    } elseif (count($parts) >= 3) {
        [$bgColor, $textColor, $dimensions] = array_slice($parts, 0, 3);
        $fontSize = null;
    } elseif (count($parts) >= 2) {
        [$textColor, $dimensions] = array_slice($parts, 0, 2);
        $bgColor = $defaults['bg_color'];
        $fontSize = null;
    } else {
        $dimensions = $parts[0] ?? $defaults['default_size'].'x'.$defaults['default_size'];
        $bgColor = $defaults['bg_color'];
        $textColor = $defaults['text_color'];
        $fontSize = null;
    }

    // Обработка размеров
    $dimParts = explode('x', $dimensions);
    $width = (int)($dimParts[0] ?? $defaults['default_size']);
    $height = (int)($dimParts[1] ?? $dimParts[0] ?? $defaults['default_size']);

    // Коррекция размеров
    if ($width === 0 && $height === 0) {
        $width = $height = $defaults['default_size'];
    }

    $maxDim = $defaults['max_dimension'] ?? 2000;
    $width = max(1, min($maxDim, $width));
    $height = max(1, min($maxDim, $height));

    // Определение текста
    $text = $params['text'] ?? "{$width}x{$height}";

    // Расчет размера шрифта
    if (is_null($fontSize)) {
        $fontSize = (int)($height * $defaults['font_ratio']);
        $fontSize = max($defaults['min_font_size'], min($defaults['max_font_size'], $fontSize));
    } else {
        $fontSize = max($defaults['min_font_size'], min($defaults['max_font_size'], $fontSize));
    }

    return [
        'width' => $width,
        'height' => $height,
        'bgColor' => $bgColor,
        'textColor' => $textColor,
        'fontSize' => $fontSize,
        'text' => $text,
        'font' => $defaults['font'],
    ];
}

/**
 * Валидация параметров
 */
function validateParameters(array $params): void
{
    if (!isValidHexColor($params['bgColor']) || !isValidHexColor($params['textColor'])) {
        header("HTTP/1.0 400 Bad Request");
        exit('Invalid color format (use 3 or 6 digit hex without #)');
    }
}

/**
 * Генерация изображения
 */
function generateImage(array &$params)
{
    $image = imagecreatetruecolor($params['width'], $params['height']);

    $bgRgb = hex2rgb($params['bgColor']);
    $textRgb = hex2rgb($params['textColor']);

    $bgColorRes = imagecolorallocate($image, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
    $textColorRes = imagecolorallocate($image, $textRgb[0], $textRgb[1], $textRgb[2]);

    $params['textColorRes'] = $textColorRes;

    imagefilledrectangle($image, 0, 0, $params['width'], $params['height'], $bgColorRes);

    if (file_exists($params['font'])) {
        renderTextWithTrueTypeFont($image, $params);
    } else {
        renderTextWithBuiltInFont($image, $params);
    }

    return $image;
}

/**
 * Рендер текста с TTF шрифтом
 */
function renderTextWithTrueTypeFont($image, array $params): void
{
    $bbox = imagettfbbox($params['fontSize'], 0, $params['font'], $params['text']);
    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[7];

    $x = ($params['width'] - $textWidth) / 2;
    $y = ($params['height'] - $textHeight) / 2 + $textHeight;

    imagettftext(
        $image,
        $params['fontSize'],
        0,
        (int)$x,
        (int)$y,
        $params['textColorRes'],
        $params['font'],
        $params['text']
    );
}


/**
 * Рендер текста со встроенным шрифтом
 */
function renderTextWithBuiltInFont($image, array $params): void
{
    $font = 5;
    $fontWidth = imagefontwidth($font);
    $fontHeight = imagefontheight($font);
    $textWidth = strlen($params['text']) * $fontWidth;

    $x = ($params['width'] - $textWidth) / 2;
    $y = ($params['height'] - $fontHeight) / 2;

    imagestring(
        $image,
        $font,
        (int)$x,
        (int)$y,
        $params['text'],
        $params['textColorRes']
    );
}

/**
 * Работа с кэшем
 */
function validateCache(array $cacheConfig): void
{
    if (!is_dir($cacheConfig['directory'])) {
        mkdir($cacheConfig['directory'], 0755, true);
    }

    if ($cacheConfig['gc_probability'] > 0 && mt_rand(1, $cacheConfig['gc_probability']) === 1) {
        cleanupCache($cacheConfig);
    }
}

function cleanupCache(array $cacheConfig): void
{
    $files = glob($cacheConfig['directory'] . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE);
    $now = time();

    foreach ($files as $file) {
        if ($now - filemtime($file) > $cacheConfig['expires']) {
            unlink($file);
        }
    }
}

function getCacheFilePath(array $cacheConfig, string $cacheKey, string $format): string
{
    return $cacheConfig['directory'] . '/' . $cacheKey . '.' . $format;
}

function trySendCachedImage(string $cacheFile, string $format, int $expires): bool
{
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $expires) {
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp'
        ];

        header('Content-Type: ' . $mimeTypes[$format]);
        header('Content-Length: ' . filesize($cacheFile));
        header('Cache-Control: public, max-age=' . ($expires / 1));
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($cacheFile)) . ' GMT');

        readfile($cacheFile);
        return true;
    }
    return false;
}

function saveToCache($image, string $cacheFile, string $format): void
{
    $tempFile = tempnam(sys_get_temp_dir(), 'imgcache');

    switch (strtolower($format)) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($image, $tempFile, 90);
            break;
        case 'webp':
            imagewebp($image, $tempFile, 90);
            break;
        case 'gif':
            imagegif($image, $tempFile);
            break;
        case 'png':
        default:
            imagepng($image, $tempFile);
            break;
    }

    rename($tempFile, $cacheFile);
    chmod($cacheFile, 0644);
}

/**
 * Отправка изображения
 */
function sendImage($image, string $format, int $expires): void
{
    $h_max_age = 'Cache-Control: public, max-age=' . $expires;
    $h_expires = 'Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';

    header($h_max_age);
    header($h_expires);

    switch (strtolower($format)) {
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            imagejpeg($image, null, 90);
            break;
        case 'webp':
            header('Content-Type: image/webp');
            imagewebp($image, null, 90);
            break;
        case 'gif':
            header('Content-Type: image/gif');
            imagegif($image);
            break;
        case 'png':
        default:
            header('Content-Type: image/png');
            imagepng($image);
            break;
    }
}

/**
 * Функция проверки hex-цвета
 *
 * @param $color
 * @return false|int
 */
function isValidHexColor($color): false|int
{
    return preg_match('/^[0-9a-f]{3,6}$/i', $color);
}

/**
 * Функция преобразования hex в rgb
 *
 * @param $hex
 * @return array
 */
function hex2rgb($hex): array
{
    // Упрощаем 3-значные цвета до 6-значных
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return [$r, $g, $b];
}

/**
 * @param $image
 * @param int $width
 * @param int $height
 * @param string $format
 * @param int $borderSize
 * @return false|GdImage|mixed|resource
 */
function addSmartBorder($image, int $width, int $height, string $format, int $borderSize = 1)
{
    if ($borderSize === 0) {
        return $image;
    }

    if (in_array(strtolower($format), ['png', 'gif'])) {
        return addTransparentBorder($image, $width, $height, $borderSize, $format);
    } else {
        return addWhiteBorder($image, $width, $height, $borderSize);
    }
}


/**
 * @param $image
 * @param int $width
 * @param int $height
 * @param int $borderSize
 * @param string $format
 * @return false|GdImage|resource
 */
function addTransparentBorder($image, int $width, int $height, int $borderSize, string $format)
{
    if ($borderSize === 0) {
        return $image;
    }

    // Создаем новое изображение с прозрачным фоном
    $newWidth = $width + ($borderSize * 2);
    $newHeight = $height + ($borderSize * 2);
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Устанавливаем прозрачность в зависимости от формата
    if ($format === 'png') {
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
    } else { // GIF
        $transparent = imagecolorallocate($newImage, 0, 0, 0);
        imagecolortransparent($newImage, $transparent);
    }

    imagefill($newImage, 0, 0, $transparent);

    // Копируем оригинальное изображение с отступом
    imagecopy(
        $newImage,
        $image,
        $borderSize,
        $borderSize,
        0,
        0,
        $width,
        $height
    );

    imagedestroy($image);
    return $newImage;
}

function addWhiteBorder($image, int $width, int $height, int $borderSize = 1)
{
    if ($borderSize === 0) {
        return $image;
    }

    // Создаем новое изображение с белым фоном
    $newWidth = $width + ($borderSize * 2);
    $newHeight = $height + ($borderSize * 2);
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Заливаем белым цветом
    $white = imagecolorallocate($newImage, 255, 255, 255);
    imagefill($newImage, 0, 0, $white);

    // Копируем оригинальное изображение с отступом
    imagecopy(
        $newImage,
        $image,
        $borderSize,
        $borderSize,
        0,
        0,
        $width,
        $height
    );

    imagedestroy($image);
    return $newImage;
}