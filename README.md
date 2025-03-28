# 🚗 Компонент "Бронирование автомобилей" для Bitrix CMS

Компонент для отображения доступных служебных автомобилей на указанный временной интервал с учетом:
- Категории комфорта автомобиля
- Должности сотрудника
- Занятости автомобилей

## Диаграмма связей
![SVG Image](https://thumb.cloud.mail.ru/weblink/thumb/xw1/bE11/L7wmuyuHF)
## Установка

1. Скопируйте папку `booking` в `/local/components/iliyazhid/`
2. Обновите компоненты в административной части Bitrix

## Требования

1. Модули Битрикс:
    - `iblock`
    - `highloadblock`

## Использование

### Параметры вызова

```php
$APPLICATION->IncludeComponent(
    "iliyazhid:booking",
    "",
    array(
        "HL_BLOCK_TABLE" => "b_carbooking_booking", // Название таблицы HL-блока
        "CARS_IBLOCK_CODE" => "CARBOOKING_CARS", // Код инфоблока автомобилей
        "POSITIONS_IBLOCK_CODE" => "CARBOOKING_POSITIONS", // Код инфоблока должностей
        "CACHE_TIME" => 3600 // Время кеширования
    ),
    false
);
```
### Параметры запроса
Компонент ожидает GET-параметры:

- `time_start` - время начала бронирования (формат: HH:MM)

- `time_end` - время окончания бронирования (формат: HH:MM)

### Результат работы
Компонент возвращает массив `$arResult` с доступными автомобилями:

```php
[
    'CARS' => [
        [
            'ID' => 123,
            'CAR_MODEL' => 'Toyota Camry',
            'COMFORT_CATEGORY' => 'Премиум',
            'DRIVER' => [
                'NAME' => 'Иванов Иван'
            ]
        ],
        // ...
    ]
]
```
## Логика работы
1. Определяет текущего авторизованного пользователя

2. Находит его должность и доступные категории комфорта

3. Выбирает автомобили соответствующих категорий

4. Фильтрует автомобили по занятости на указанный интервал времени
