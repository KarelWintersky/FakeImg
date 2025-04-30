<?php

namespace FakeImageSrc;

use GdImage;

class ImageProcessor_GD implements ImageProcessorInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function generateImage(array $imageParams)
    {
        $image = imagecreatetruecolor($imageParams['width'], $imageParams['height']);

        $bgRgb = Common::hex2rgb($imageParams['bgColor']);
        $textRgb = Common::hex2rgb($imageParams['textColor']);

        $bgColorRes = imagecolorallocate($image, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        $textColorRes = imagecolorallocate($image, $textRgb[0], $textRgb[1], $textRgb[2]);

        $imageParams['textColorRes'] = $textColorRes;

        imagefilledrectangle($image, 0, 0, $imageParams['width'], $imageParams['height'], $bgColorRes);

        if (file_exists($imageParams['font_file'])) {
            self::renderTextWithTrueTypeFont($image, $imageParams);
        } else {
            self::renderTextWithBuiltInFont($image, $imageParams);
        }

        return $image;
    }

    public function addSmartBorder($image, int $width, int $height, string $format, int $borderSize = 1):mixed
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

    public function saveToCache($image, string $cacheFile, string $format): void
    {
        /**
         * @var GdImage $image
         */

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

    public function sendImage($image, string $format, int $expires): void
    {
        /**
         * @var GdImage $image
         */

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
     * Рендер текста с TTF шрифтом
     */
    public static function renderTextWithTrueTypeFont($image, array $params): void
    {
        $bbox = imagettfbbox($params['fontSize'], 0, $params['font_file'], $params['text']);
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
            $params['font_file'],
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

    public function imageDestroy($image)
    {
        return imagedestroy($image);
    }
}