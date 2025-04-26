# FakeImg - Генератор placeholder-изображений

Замена сервису https://placehold.jp/en.html и аналогичным.  

## Описание

FakeImg - это простой и быстрый генератор placeholder-изображений с возможностью настройки размеров, цвета фона, 
текста и других параметров через URL. 

Проект написан на PHP и использует GD библиотеку для генерации изображений.

## Возможности

- Генерация изображений различных размеров
- Настройка цвета фона и текста
- Поддержка форматов: PNG, JPEG, WebP, GIF
- Автоматическое центрирование текста
- Кэширование изображений для повышения производительности
- Защита от доступа к служебным файлам

## Быстрый старт

### URL структура

```
[/{размер_шрифта}][/{цвет_фона}/{цвет_текста}]/{ширина}x{высота}.{формат}
```

### Примеры использования

1. Простое изображение:
   ```
   /300x150.png
   ```
   
2. С указанием цветов:
   ```
   /3d4070/ffffff/300x150.png
   ```

3. С кастомным текстом размера 30:
   ```
   /30/3d4070/ffffff/300x150.png?text=Custom%20text
   ```

4. С указанием другого формата:
   ```
   /3d4070/ffffff/300x150.gif
   /3d4070/ffffff/300x150.webp
   /3d4070/ffffff/300x150.png
   ```

### Параметры запроса

- `text` - текст для отображения (по умолчанию - линейные размеры баннера)

## Установка

1. Cкачайте релиз (DEB-пакет)
2. Установите (`dpkg -i fakeimg_1.0.0_all.deb`)
3. Настройте веб-сервер (см. ниже)

## Конфигурация Nginx

Пример конфигурации Nginx (тестовая среда, без кэширования):

```nginx
server {
    set $php_handler php-handler-8-3; # заменить на свой
    set $index_html index.html;
    set $index_php  index.php;

    listen 80;
    server_name fakeimg.local; # заменить на свой
    root /var/www/fakeimg/public/; 

    index $index_html $index_php;

    location = / {
        try_files $uri $uri/ /$index_html;
    }

    location / {
        try_files $uri $uri/ /$index_php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass $php_handler;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_index $index_php;
    }

    location ~* favicon\.(ico|png|jpg|jpeg|gif|webp|svg)$ {
        access_log      off;
        log_not_found   off;
        expires 7d;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

     location ~* ^/(\.git|swagger|\.well-known|wp-|\.env) {
        deny all;
        access_log off;
        log_not_found off;
        return 404;
    }

    access_log /var/log/nginx/fakeimg_access.log;
    error_log /var/log/nginx/fakeimg_error.log;
}
```

Продакшен-среда, с FastCGI-кэшированием:
```
fastcgi_cache_path /dev/shm/fakeimg_cache levels=1:2 keys_zone=FAKECACHE:100m inactive=24h max_size=1g use_temp_path=off;
fastcgi_cache_key "$scheme$request_method$host$request_uri";

server {
    set $php_handler php-handler-8-3;
    set $index_html _index.html;
    set $index_php  _index.php;
    set $skip_cache 0;

    listen 80;
    server_name fakeimg.local;
    root /var/www/FakeImg/public/;

    index $index_html $index_php;

    # Базовые настройки кэширования
    fastcgi_cache FAKECACHE;
    fastcgi_cache_valid 200 301 302 12h;
    fastcgi_cache_min_uses 1;
    fastcgi_cache_use_stale error timeout updating invalid_header http_500 http_503;
    fastcgi_cache_background_update on;
    fastcgi_cache_lock on;
    add_header X-Cache-Status $upstream_cache_status;

    location = / {
        try_files $uri $uri/ /$index_html;
    }

    location / {
        try_files $uri $uri/ /$index_php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass $php_handler;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_index $index_php;

        # Параметры кэширования
        fastcgi_cache_bypass $skip_cache;
        fastcgi_no_cache $skip_cache;

        # Дополнительные заголовки для отладки
        add_header X-Cache-Date $upstream_http_date;
        add_header X-Cache-Expires $upstream_http_expires;
    }

    location ~* favicon\.(ico|png|jpg|jpeg|gif|webp|svg)$ {
        access_log      off;
        log_not_found   off;
        expires 7d;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

    access_log /var/log/nginx/fakeimg_access.log;
    error_log /var/log/nginx/fakeimg_error.log;
}
```

## Настройки

Основные настройки можно изменить в файле конфигурации `config.php`

```php
return [
    'cache' => [
        'enabled' => false,
        'directory' => __DIR__ . '/../cache',
        'expires' => 1,
        'gc_probability' => 10,
        'cacheFile' =>  '',
    ],
    'defaults' => [
        'font_size' => 30,
        'bg_color' => '3d4070',
        'text_color' => 'ffffff',
        'default_size' => 150,
        'default_text' => null,
        'default_format' => 'png',
        'min_font_size' => 8,
        'max_font_size' => 100,
        'font_ratio' => 0.15,
        'font' => __DIR__ . '/fonts/segoe-ui.ttf',
        'max_dimension' => 2000,

        // Замените на свои домены
        'protected_domains' => ['fakeimg.local'],

        // Размер прозрачной рамки в пикселях
        'border_size' => 1,
        'transparent_formats' => ['png', 'gif'], // Форматы с поддержкой прозрачности
    ]
];
```

## Разработка

Для участия в разработке:

1. Форкните репозиторий
2. Создайте ветку для вашей фичи (`git checkout -b feature/amazing-feature`)
3. Закоммитьте изменения (`git commit -m 'Add some amazing feature'`)
4. Запушьте в ветку (`git push origin feature/amazing-feature`)
5. Откройте Pull Request

## Лицензия

Этот проект распространяется под лицензией MIT. См. файл [LICENSE](LICENSE) для получения дополнительной информации.