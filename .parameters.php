<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = array(
    "PARAMETERS" => array(
        "HL_BLOCK_TABLE" => array(
            "NAME" => "Название таблицы Hl блока бронирований",
            "TYPE" => "STRING",
            "GROUP" => "BASE",
            "DEFAULT" => "",
        ),
        "CARS_IBLOCK_CODE" => array(
            "NAME" => "Код инфоблока автомобилей",
            "TYPE" => "STRING",
            "GROUP" => "BASE",
            "DEFAULT" => "",
        ),
        "POSITIONS_IBLOCK_CODE" => array(
            "NAME" => "Код инфоблока должностей",
            "TYPE" => "STRING",
            "GROUP" => "BASE",
            "DEFAULT" => "",
        ),
        "CACHE_TIME" => array("DEFAULT" => "3600"),
    ),
);
