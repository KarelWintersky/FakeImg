<?php

(new DateTime)->setTimezone(new DateTimeZone('Europe/Moscow'));

require_once __DIR__ . '/functions.php';

// Конфигурация кэширования
$cacheConfig = [
    'enabled' => false,                  // Включить кэширование
    'directory' => __DIR__ . '/../cache',  // Папка для кэша
    'expires' => 1,                 // Время жизни кэша в секундах (1 день)
    'gc_probability' => 10,             // Вероятность запуска сборщика мусора (1/10)
];

// Установка параметров по умолчанию
$defaults = [
    'font_size' => 30,
    'bg_color' => '3d4070',
    'text_color' => 'ffffff',
    'default_size' => 150,
    'default_text' => null,
    'default_format' => 'png',

    'min_font_size' => 8,
    'max_font_size' => 100,
    'font_ratio' => 0.15, // 15% от высоты изображения
    // font
    'font'      =>  __DIR__ . '/fonts/segoe-ui.ttf'
];

// Получаем путь и параметры запроса
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
parse_str($query, $params);

// Разбираем путь
$parts = explode('/', trim($path, '/'));

// Создаем папку для кэша если нужно
if ($cacheConfig['enabled']) {
    if (!is_dir($cacheConfig['directory'])) {
        mkdir($cacheConfig['directory'], 0755, true);
    }

    $cacheKey = getCacheKey($defaults);
    $format = pathinfo(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), PATHINFO_EXTENSION);
    $format = in_array($format, ['png', 'jpg', 'jpeg', 'webp']) ? $format : $defaults['default_format'];
    $cacheFile = $cacheConfig['directory'] . '/' . $cacheKey . '.' . $format;

    // Если файл в кэше существует и не устарел - отдаем его
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheConfig['expires']) {
        sendCachedImage($cacheFile, $format);
        exit(1);
    }

    // С вероятностью 1/gc_probability запускаем сборщик мусора
    if (mt_rand(1, $cacheConfig['gc_probability']) === 1) {
        $files = glob($cacheConfig['directory'] . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE);
        $now = time();
        foreach ($files as $file) {
            if ($now - filemtime($file) > $cacheConfig['expires']) {
                unlink($file);
            }
        }
    }
}

// код выше
// Получаем путь и параметры запроса
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
parse_str($query, $params);

// Разбираем путь
$parts = explode('/', trim($path, '/'));

// Определяем формат изображения из последней части URL
$lastPart = end($parts);
$format = pathinfo($lastPart, PATHINFO_EXTENSION);
if (!in_array($format, ['png', 'jpg', 'jpeg', 'webp'])) {
    $format = $defaults['default_format'];
}

// Удаляем расширение из последней части
$parts[count($parts)-1] = pathinfo($lastPart, PATHINFO_FILENAME);

// Обработка параметров в зависимости от структуры URL
if (count($parts) >= 4 && is_numeric($parts[0])) {
    // Формат: /font-size/bg-color/text-color/widthxheight
    $fontSize = (int)$parts[0];
    $bgColor = $parts[1] ?? $defaults['bg_color'];
    $textColor = $parts[2] ?? $defaults['text_color'];
    $dimensions = $parts[3];
} elseif (count($parts) >= 3) {
    // Формат: /bg-color/text-color/widthxheight
    $fontSize = null;
    $bgColor = $parts[0] ?? $defaults['bg_color'];
    $textColor = $parts[1] ?? $defaults['text_color'];
    $dimensions = $parts[2];
} elseif (count($parts) >= 2) {
    // Формат: /text-color/widthxheight
    $fontSize = null;
    $bgColor = $defaults['bg_color'];
    $textColor = $parts[0] ?? $defaults['text_color'];
    $dimensions = $parts[1];
} else {
    // Формат: /widthxheight
    $fontSize = null;
    $bgColor = $defaults['bg_color'];
    $textColor = $defaults['text_color'];
    $dimensions = $parts[0] ?? $defaults['default_size'].'x'.$defaults['default_size'];
}

// Разбор размеров
$dimParts = explode('x', $dimensions);
$width = (int)($dimParts[0] ?? $defaults['default_size']);
$height = (int)($dimParts[1] ?? $dimParts[0] ?? $defaults['default_size']);

// Обработка 0x0
if ($width === 0 && $height === 0) {
    $width = $height = $defaults['default_size'];
}

// Проверяем минимальный и максимальный размер
$width = max(1, min(2000, $width));
$height = max(1, min(2000, $height));

// Получаем текст
$text = $params['text'] ?? "{$width}x{$height}";

// Проверяем цвета
if (!isValidHexColor($bgColor) || !isValidHexColor($textColor)) {
    header("HTTP/1.0 400 Bad Request");
    exit('Invalid color format (use 3 or 6 digit hex without #)');
}

if (is_null($fontSize)) {
    $fontSize = (int)($height * $defaults['font_ratio']);
    $fontSize = max($defaults['min_font_size'], min($defaults['max_font_size'], $fontSize));
} else {
    $fontSize = max($defaults['min_font_size'], min($defaults['max_font_size'], $fontSize));
}

// Создаем изображение
$image = imagecreatetruecolor($width, $height);

$bgRgb = hex2rgb($bgColor);
$textRgb = hex2rgb($textColor);

$bgColorRes = imagecolorallocate($image, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
$textColorRes = imagecolorallocate($image, $textRgb[0], $textRgb[1], $textRgb[2]);

// Заливаем фон
imagefilledrectangle($image, 0, 0, $width, $height, $bgColorRes);

// Добавляем текст
$font = $defaults['font']; // Путь к файлу шрифта

if (file_exists($font)) {
    // Получаем реальные границы текста
    $bbox = imagettfbbox($fontSize, 0, $font, $text);

    // Рассчитываем РЕАЛЬНУЮ ширину и высоту текста
    /*
        [0] => X левого нижнего
        [1] => Y левого нижнего
        [2] => X правого нижнего
        [3] => Y правого нижнего
        [4] => X правого верхнего
        [5] => Y правого верхнего
        [6] => X левого верхнего
        [7] => Y левого верхнего
     */
    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[7]; // Это важное исправление!

    // Координаты для ИДЕАЛЬНОГО центрирования
    $x = ($width - $textWidth) / 2;
    $y = ($height - $textHeight) / 2 + $textHeight  /* - ($textHeight * 0.2)*/; // Дополнительная коррекция

    imagettftext($image, $fontSize, 0, (int)$x, (int)$y, $textColorRes, $font, $text);
} else {
    // Fallback на встроенный шрифт с центрированием
    $font = 5;
    $fontWidth = imagefontwidth($font);
    $fontHeight = imagefontheight($font);
    $textWidth = strlen($text) * $fontWidth;

    $x = ($width - $textWidth) / 2;
    $y = ($height - $fontHeight) / 2;

    imagestring($image, $font, (int)$x, (int)$y, $text, $textColorRes);
}
//
// После генерации изображения сохраняем в кэш
if ($cacheConfig['enabled']) {
    // Сохраняем изображение во временный файл
    $tempFile = tempnam(sys_get_temp_dir(), 'imgcache');

    switch (strtolower($format)) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($image, $tempFile, 90);
            break;
        case 'webp':
            imagewebp($image, $tempFile, 90);
            break;
        case 'png':
        default:
            imagepng($image, $tempFile);
            break;
    }

    // Переносим в кэш атомарно
    rename($tempFile, $cacheFile);

    // Устанавливаем права
    chmod($cacheFile, 0644);
}

// Отправляем изображение
$h_max_age = 'Cache-Control: public, max-age=' . ($cacheConfig['expires'] / 1);
$h_expires = 'Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheConfig['expires']) . ' GMT';
switch (strtolower($format)) {
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        header($h_max_age);
        header($h_expires);
        imagejpeg($image, null, 90);
        break;
    case 'webp':
        header('Content-Type: image/webp');
        header($h_max_age);
        header($h_expires);
        imagewebp($image, null, 90);
        break;
    case 'png':
    default:
        header('Content-Type: image/png');
        header($h_max_age);
        header($h_expires);
        imagepng($image);
        break;
}

imagedestroy($image);

