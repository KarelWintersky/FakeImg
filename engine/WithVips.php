<?php

namespace FakeImageSrc;

use Jcupitt\Vips;
use Jcupitt\Vips\Exception;

class WithVips
{
    /**
     * @param array $request
     * @param array $config
     * @return Vips\Image
     * @throws Vips\Exception
     */
    public static function generateImage(array $request, array $config): Vips\Image
    {
        // Создаем базовое изображение
        $bgColor = Common::hex2rgb($request['bg_color'] ?? $config['defaults']['bg_color']);

        $image = Vips\Image::newFromArray([$bgColor])->embed(
            0, 0,
            $request['width'],
            $request['height'],
            ['extend' => Vips\Extend::COPY]
        );

        // Добавляем текст
        $text = $request['text'] ?? "{$request['width']}x{$request['height']}";
        $fontSize = self::calculateFontSize($request, $config, $text);

        $textImage = Vips\Image::text($text, [
            'font' => $config['defaults']['font_file'],
            'width' => $request['width'] - 20,
            'height' => $request['height'] - 20,
            'size' => $fontSize,
            'align' => Vips\Align::CENTRE,
            'rgba' => true,
            'fontfile' => $config['defaults']['font_file'],
            'dpi' => 72,
        ]);

        // Накладываем текст на изображение
        $image = $image->composite2(
            $textImage,
            Vips\BlendMode::OVER,
            [
                'x' => (int)(($request['width'] - $textImage->width) / 2),
                'y' => (int)(($request['height'] - $textImage->height) / 2),
            ]
        );

        // Добавляем прозрачную рамку для внутренних запросов
        if ($request['internal'] && in_array($request['format'], ['png', 'gif'])) {
            $image = self::addTransparentBorder($image, $request['width'], $request['height'], 1);
        }

        return $image;
    }

    /**
     * Добавление прозрачной рамки
     */
    public static function addTransparentBorder(Vips\Image $image, int $width, int $height, int $borderSize): Vips\Image
    {
        $newWidth = $width + $borderSize * 2;
        $newHeight = $height + $borderSize * 2;

        return $image->embed(
            $borderSize,
            $borderSize,
            $newWidth,
            $newHeight,
            ['extend' => Vips\Extend::BACKGROUND, 'background' => [0, 0, 0, 0]]
        );
    }

    /**
     * Отправка изображения
     * @throws Exception
     */
    public static function sendImage(Vips\Image $image, string $format): void
    {
        header("Cache-Control: public, max-age=3600");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

        switch (strtolower($format)) {
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                echo $image->writeToBuffer('.jpg', ['Q' => 90]);
                break;
            case 'webp':
                header('Content-Type: image/webp');
                echo $image->writeToBuffer('.webp', ['Q' => 90]);
                break;
            case 'gif':
                header('Content-Type: image/gif');
                echo $image->writeToBuffer('.gif');
                break;
            case 'png':
            default:
                header('Content-Type: image/png');
                echo $image->writeToBuffer('.png');
                break;
        }
    }

    public static function calculateFontSize(array $request, array $config, string $text): float
    {
        $maxWidth = $request['width'] * 0.9; // 90% ширины изображения
        $maxHeight = $request['height'] * 0.8; // 80% высоты

        // Начальный размер на основе высоты
        $fontSize = (float)($request['height'] * $config['defaults']['font_ratio']);

        // Проверяем, помещается ли текст
        $testText = Vips\Image::text($text, [
            'font' => $config['defaults']['font_file'],
            'size' => $fontSize,
            'width' => $maxWidth,
            'height' => $maxHeight,
            'rgba' => true
        ]);

        // Если текст не помещается - уменьшаем шрифт
        while (($testText->width > $maxWidth || $testText->height > $maxHeight)
            && $fontSize > $config['defaults']['min_font_size']) {
            $fontSize -= 1;
            $testText = Vips\Image::text($text, [
                'font' => $config['defaults']['font_file'],
                'size' => $fontSize,
                'width' => $maxWidth,
                'height' => $maxHeight,
                'rgba' => true
            ]);
        }

        return max($config['defaults']['min_font_size'],
            min($config['defaults']['max_font_size'], $fontSize));
    }



}