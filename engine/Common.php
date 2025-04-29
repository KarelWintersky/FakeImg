<?php

namespace FakeImageSrc;

class Common
{
    /**
     * Проверяет, является ли запрос внутренним (с одного из защищенных доменов)
     *
     * @param string $referer
     * @param array $allowedDomains
     * @return bool
     */
    public static function checkReferer(string $referer, array $allowedDomains): bool
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
    public static function isInternalRequest(array $params, array $config): bool
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
    public static function parseRequest(string $requestUri, array $defaults): array
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

    public static /**
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
    public static function validateParameters(array $params): void
    {
        if (!self::isValidHexColor($params['bgColor']) || !self::isValidHexColor($params['textColor'])) {
            header("HTTP/1.0 400 Bad Request");
            exit('Invalid color format (use 3 or 6 digit hex without #)');
        }
    }

    /**
     * Работа с кэшем
     */
    public static function validateCache(array $cacheConfig): void
    {
        if (!is_dir($cacheConfig['directory'])) {
            mkdir($cacheConfig['directory'], 0755, true);
        }

        if ($cacheConfig['gc_probability'] > 0 && mt_rand(1, $cacheConfig['gc_probability']) === 1) {
            self::cleanupCache($cacheConfig);
        }
    }

    public static function cleanupCache(array $cacheConfig): void
    {
        $files = glob($cacheConfig['directory'] . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE);
        $now = time();

        foreach ($files as $file) {
            if ($now - filemtime($file) > $cacheConfig['expires']) {
                unlink($file);
            }
        }
    }

    public static function getCacheFilePath(array $cacheConfig, string $cacheKey, string $format): string
    {
        return $cacheConfig['directory'] . '/' . $cacheKey . '.' . $format;
    }

    public static function trySendCachedImage(string $cacheFile, string $format, int $expires): bool
    {
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $expires) {
            $mimeTypes = [
                'png'   =>  'image/png',
                'jpg'   =>  'image/jpeg',
                'jpeg'  =>  'image/jpeg',
                'webp'  =>  'image/webp',
                'gif'   =>  'image/gif'
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

    /**
     * Функция проверки hex-цвета
     *
     * @param $color
     * @return false|int
     */
    public static function isValidHexColor($color): false|int
    {
        return preg_match('/^[0-9a-f]{3,6}$/i', $color);
    }

    /**
     * Функция преобразования hex в rgb
     *
     * @param $hex
     * @return array
     */
    public static function hex2rgb($hex): array
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
     * @param $defaults
     * @return string
     */
    public static function getCacheKey($defaults): string
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




}