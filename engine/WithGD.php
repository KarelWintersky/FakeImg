<?php

namespace FakeImageSrc;

use GdImage;

class WithGD
{
    /**
     * Генерация изображения
     */
    public static function generateImage(array &$params)
    {
        $image = imagecreatetruecolor($params['width'], $params['height']);

        $bgRgb = Common::hex2rgb($params['bgColor']);
        $textRgb = Common::hex2rgb($params['textColor']);

        $bgColorRes = imagecolorallocate($image, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        $textColorRes = imagecolorallocate($image, $textRgb[0], $textRgb[1], $textRgb[2]);

        $params['textColorRes'] = $textColorRes;

        imagefilledrectangle($image, 0, 0, $params['width'], $params['height'], $bgColorRes);

        if (file_exists($params['font'])) {
            self::renderTextWithTrueTypeFont($image, $params);
        } else {
            self::renderTextWithBuiltInFont($image, $params);
        }

        return $image;
    }

    /**
     * Рендер текста с TTF шрифтом
     */
    public static function renderTextWithTrueTypeFont($image, array $params): void
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
    public static function renderTextWithBuiltInFont($image, array $params): void
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

    public static function saveToCache($image, string $cacheFile, string $format): void
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
    public static function sendImage($image, string $format, int $expires): void
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
     * @param $image
     * @param int $width
     * @param int $height
     * @param string $format
     * @param int $borderSize
     * @return false|GdImage|mixed|resource
     */
    public static function addSmartBorder($image, int $width, int $height, string $format, int $borderSize = 1)
    {
        if ($borderSize === 0) {
            return $image;
        }

        if (in_array(strtolower($format), ['png', 'gif'])) {
            return self::addTransparentBorder($image, $width, $height, $borderSize, $format);
        } else {
            return self::addWhiteBorder($image, $width, $height, $borderSize);
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
    public static function addTransparentBorder($image, int $width, int $height, int $borderSize, string $format)
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

    public static function addWhiteBorder($image, int $width, int $height, int $borderSize = 1)
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

}