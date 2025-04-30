<?php

namespace FakeImageSrc;

use Jcupitt\Vips;
use Jcupitt\Vips\Exception;
use Jcupitt\Vips\Image;

class ImageProcessor_Vips implements ImageProcessorInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    private function renderText(Image $image, array $imageParams)
    {
        // Добавляем текст
        $text = $request['text'] ?? "{$imageParams['width']}x{$imageParams['height']}";
        // $fontSize = self::calculateFontSize($imageParams, $this->config, $text);

        if (file_exists($this->config['defaults']['font_file'])) {
            $textImage = Image::text($text, [
                'font' => $this->config['defaults']['font_file'],
                'width' => $imageParams['width'] - 20,
                'height' => $imageParams['height'] - 20,
                'align' => Vips\Align::CENTRE,
                'rgba' => true,
                'fontfile' => $this->config['defaults']['font_file'],
            ]);
        } else {
            $textImage = Vips\Image::text(
                $text,
                [
                    'font' => 'sans', // Встроенный шрифт (sans, serif, mono)
                    'width' => $image->width,
                    'height' => $image->height,
                    'align' => Vips\Align::CENTRE,
                    'rgba' => true
                ]
            );
        }
        $color
            = Image::newFromArray([ Common::hex2rgb($imageParams['textColor']) ])
            ->embed(0, 0, $textImage->width, $textImage->height, ['extend' => 'copy'])
            /*->bandjoin($textImage)*/;

        // Создаем цветное изображение текста
        $coloredText = Vips\Image::black($image->width, $image->height)
            ->add([ Common::hex2rgb($imageParams['textColor'])  ])
            /*->bandjoin(255)*/; // Добавляем альфа-канал

        // Накладываем текст на изображение
        $image = $image->composite2(
            $color,
            Vips\BlendMode::OVER,
            [
                'x' => (int)(($imageParams['width'] - $textImage->width) / 2),
                'y' => (int)(($imageParams['height'] - $textImage->height) / 2),
            ]
        );

        return $image;
    }

    /**
     * @inheritDoc
     */
    /**
     * @param array $imageParams
     * @return Image
     * @throws Vips\Exception
     */
    public function generateImage(array $imageParams): Image
    {
        // Создаем базовое изображение
        $bgColor = Common::hex2rgb($imageParams['bgColor'] ?? $this->config['defaults']['background_color']);

        $image
            = Vips\Image::black($imageParams['width'], $imageParams['height'])
            ->add($bgColor)
            ->cast('uchar');

        $image = self::renderText($image, $imageParams);

        return $image;
    }

    /**
     * @inheritDoc
     */
    public function addSmartBorder($image, int $width, int $height, string $format, int $borderSize = 1): mixed
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
     * @inheritDoc
     */
    public function saveToCache($image, string $cacheFile, string $format): void
    {
        /**
         * @var Image $image
         */
        if (!($image instanceof Image)) {
            throw new \InvalidArgumentException('Первый аргумент должен быть объектом Image libvips');
        }

        $format = strtolower($format);
        $options = [];

        // Настройки для разных форматов
        switch ($format) {
            case 'jpg':
            case 'jpeg':
                $options = [
                    'Q' => 90,          // Качество (0-100)
                    'optimize_coding' => true,
                    'strip' => true    // Удаление метаданных
                ];
                break;

            case 'webp':
                $options = [
                    'Q' => 90,          // Качество (0-100)
                    'lossless' => false,
                    'strip' => true
                ];
                break;

            case 'png':
                $options = [
                    'compression' => 6, // Уровень сжатия (0-9)
                    'filter' => 0x08,   // FILTER_NONE
                    'strip' => true
                ];
                break;

            default:
                throw new \InvalidArgumentException("Неподдерживаемый формат: {$format}");
        }

        try {
            // Создаем директорию для кэша, если ее нет
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true)) {
                throw new \RuntimeException("Не удалось создать директорию кэша: {$cacheDir}");
            }

            // Сохраняем изображение
            $image->writeToFile($cacheFile, $options);

            // Устанавливаем корректные права
            chmod($cacheFile, 0644);

        } catch (\Exception $e) {
            throw new \RuntimeException("Ошибка сохранения в кэш: " . $e->getMessage(), 0, $e);
        }

    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function sendImage($image, string $format, int $expires): void
    {
        $h_max_age = 'Cache-Control: public, max-age=' . $expires;
        $h_expires = 'Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';

        header($h_max_age);
        header($h_expires);

        /**
         * @var Image $image
         */

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

    ########################################################################

    public static function calculateFontSize(array $request, array $config, string $text): float
    {
        $maxWidth = $request['width'] * 0.9; // 90% ширины изображения
        $maxHeight = $request['height'] * 0.8; // 80% высоты

        // Начальный размер на основе высоты
        $fontSize = (float)($request['height'] * $config['defaults']['font_ratio']);

        // Проверяем, помещается ли текст
        /*
         * PHP Fatal error:  Uncaught Jcupitt\Vips\Exception: optional argument 'size' does not exist in
         * /var/www/FakeImg/vendor/jcupitt/vips/src/VipsOperation.php:299
         */
        $testText = Image::text($text, [
            'font' => $config['defaults']['font_file'],
            // 'size' => $fontSize,
            'width' => $maxWidth,
            'height' => $maxHeight,
            'rgba' => true
        ]);

        // Если текст не помещается - уменьшаем шрифт
        while (($testText->width > $maxWidth || $testText->height > $maxHeight)
            && $fontSize > $config['defaults']['min_font_size']) {
            $fontSize -= 1;
            $testText = Image::text($text, [
                'font' => $config['defaults']['font_file'],
                // 'size' => $fontSize,
                'width' => $maxWidth,
                'height' => $maxHeight,
                'rgba' => true
            ]);
        }

        return max($config['defaults']['min_font_size'],
            min($config['defaults']['max_font_size'], $fontSize));
    }

    /**
     * // Добавляем прозрачную рамку для внутренних запросов
     */
    public static function addTransparentBorder(Image $image, int $width, int $height, int $borderSize): Image
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
     * Добавляет белую рамку к изображению
     *
     * @param Image $image Исходное изображение
     * @param int $width Ширина исходного изображения
     * @param int $height Высота исходного изображения
     * @param int $borderSize Толщина рамки в пикселях
     * @return Image Изображение с белой рамкой
     */
    public static function addWhiteBorder(Image $image, int $width, int $height, int $borderSize): Image
    {
        $newWidth = $width + $borderSize * 2;
        $newHeight = $height + $borderSize * 2;

        return $image->embed(
            $borderSize,
            $borderSize,
            $newWidth,
            $newHeight,
            [
                'extend' => Vips\Extend::BACKGROUND,
                'background' => [255, 255, 255] // Белый цвет (R, G, B)
            ]
        );
    }

    public static function calculateCenteredPosition(
        Vips\Image $image,
        string $text,
        int $fontSize
    ): array {
        $textImage = Vips\Image::text($text, ['font' => 'sans', 'size' => $fontSize]);
        $x = (int)(($image->width - $textImage->width) / 2);
        $y = (int)(($image->height + $textImage->height) / 2);
        return [$x, $y];
    }

    public function imageDestroy($image)
    {
        //
    }
}