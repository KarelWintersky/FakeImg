<?php

return [
    // use processor: 'gd', 'vips'
    'processor' =>  'gd',
    'cache' => [
        'enabled' => false,                      // включено ли кэширование?
        'directory' => __DIR__ . '/../cache',   // путь к кэшу
        'expires' => 1,                         // время кэширования в секундах
        'gc_probability' => 10,                 // с вероятностью 1/N устаревшие файлы будут очищены
    ],
    'defaults' => [
        'default_width'     =>  300,
        'default_height'    =>  200,

        'background_color'  =>  '3d4070',
        'text_color'        =>  'ffffff',

        'font_size' => 30,

        'default_size' => 150,
        'default_text' => null,

        'format'            => 'png',   // default output file format, one of ['png', 'jpg', 'jpeg', 'webp', 'gif']

        'min_font_size' => 8,
        'max_font_size' => 100,
        'font_ratio' => 0.15,

        'font_file' => __DIR__ . '/fonts/segoe-ui.ttf',
        'max_dimension' => 2000,

        // Замените на свои домены
        'protected_domains' => ['fakeimg.local'],

        // Размер прозрачной рамки в пикселях
        'border_size' => 1,
        'transparent_formats' => ['png', 'gif'], // Форматы с поддержкой прозрачности
    ]
];
