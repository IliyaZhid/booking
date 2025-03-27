<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\HighloadBlock\HighloadBlockTable;
use \Bitrix\Main\AccessDeniedException;
use \Bitrix\Main\ArgumentException;
use \Bitrix\Main\ArgumentNullException;
use \Bitrix\Main\ArgumentOutOfRangeException;
use \Bitrix\Main\ArgumentTypeException;
use \Bitrix\Main\Engine\CurrentUser;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\ObjectNotFoundException;
use \Bitrix\Main\SystemException;
use \Bitrix\Main\Type\DateTime;

/**
 * Project: Iliyazhid.carbooking
 * Date: 2025-03-27
 * Time: 19:57:08
 */
class AvailableCarsComponent extends \CBitrixComponent
{
    const REQUIRED_MODULES = ['iblock', 'highloadblock'];
    const DATE_FORMAT = 'd.m.Y';
    const DATE_TIME_FORMAT = 'd.m.Y H:i';

    private int $currentUserId;
    private DateTime $dateStart;
    private DateTime $dateEnd;

    public function onPrepareComponentParams($arParams)
    {
        $requiredParams = [
            'HL_BLOCK_TABLE' => 'CARBOOKING_HL_BLOCK_TABLE_REQUIRED',
            'CARS_IBLOCK_CODE' => 'CARBOOKING_CARS_IBLOCK_CODE_REQUIRED',
            'POSITIONS_IBLOCK_CODE' => 'CARBOOKING_POSITIONS_IBLOCK_CODE_REQUIRED'
        ];

        foreach ($requiredParams as $param => $errorMessage) {
            if (empty($arParams[$param])) {
                throw new ArgumentException(Loc::getMessage($errorMessage));
            }
        }

        return [
            'HL_BLOCK_TABLE' => (string)$arParams['HL_BLOCK_TABLE'],
            'CARS_IBLOCK_CODE' => (string)$arParams['CARS_IBLOCK_CODE'],
            'POSITIONS_IBLOCK_CODE' => (string)$arParams['POSITIONS_IBLOCK_CODE'],
            'CACHE_TIME' => isset($arParams['CACHE_TIME']) ? (int)$arParams['CACHE_TIME'] : 3600,
        ];
    }

    public function executeComponent()
    {
        try {
            $this->checkRequiredModules();
            $this->checkAuth();
            $this->getTimeInterval();

            if ($this->StartResultCache($this->arParams['CACHE_TIME'], $this->currentUserId)) {
                $categories = $this->getAvailableCategories();
                $this->arResult['CARS'] = $this->getCarsByCategories($categories);
                $this->endResultCache();
            }

            $this->arResult['CARS'] = $this->checkAvailability($this->arResult['CARS']);

            $this->includeComponentTemplate();

        } catch (SystemException $e) {
            $this->AbortResultCache();
            ShowError($e->getMessage());
            return;
        }
    }

    private function checkRequiredModules(): void
    {
        foreach (self::REQUIRED_MODULES as $sModuleName) {
            if (!CModule::IncludeModule($sModuleName)) {
                throw new ObjectNotFoundException(Loc::getMessage('CARBOOKING_ERROR_MODULE', ['#MODULE#' => $sModuleName]));
            }
        }
    }

    private function checkAuth(): void
    {
        $this->currentUserId = CurrentUser::get()->getId();

        if ($this->currentUserId <= 0) {
            throw new AccessDeniedException(Loc::getMessage('CARBOOKING_AUTH_REQUIRED'));
        }
    }

    private function getTimeInterval(): void
    {
        $timeStart = $this->request->get('time_start');
        $timeEnd = $this->request->get('time_end');

        if (empty($timeStart) || empty($timeEnd)) {
            throw new ArgumentNullException(Loc::getMessage('CARBOOKING_ARGUMENTS'));
        }

        $now = new DateTime();
        $today = $now->format(self::DATE_FORMAT);

        try {
            $this->dateStart = new DateTime("$today $timeStart", self::DATE_TIME_FORMAT);
            $this->dateEnd = new DateTime("$today $timeEnd", self::DATE_TIME_FORMAT);
        } catch (SystemException) {
            throw new ArgumentTypeException(Loc::getMessage('CARBOOKING_ARGUMENTS'));
        }

        // Пограничные случаи
        if ($timeStart >= $timeEnd) {
            // Ночной интервал (23:00-01:00)
            $this->dateEnd->add('+1 day');
        } elseif ($this->dateStart < $now) {
            // Время начала уже прошло сегодня
            $this->dateStart->add('+1 day');
            $this->dateEnd->add('+1 day');
        }

        if ($this->dateStart >= $this->dateEnd) {
            throw new ArgumentOutOfRangeException(Loc::getMessage('CARBOOKING_ARGUMENTS'));
        }
    }

    private function getAvailableCategories(): array
    {
        $arAvailableCategories = [];

        $rsPosition = \CIBlockElement::GetList([], [
            'ACTIVE' => 'Y',
            'IBLOCK_CODE' => $this->arParams['POSITIONS_IBLOCK_CODE'],
            'PROPERTY_USERS_LINK' => $this->currentUserId
        ], false, false, ['NAME', "PROPERTY_COMFORT_CATEGORIES_LINK"]);

        if ($rsPosition->SelectedRowsCount()) {
            while ($arPosition = $rsPosition->fetch()) {
                $arAvailableCategories[] = $arPosition['PROPERTY_COMFORT_CATEGORIES_LINK_VALUE'];
            }
        } else {
            throw new ObjectNotFoundException(Loc::getMessage('CARBOOKING_POSITION_NOT_FOUND'));
        }

        return $arAvailableCategories;
    }

    private function getCarsByCategories(array $arComfortCategories = null): array
    {
        $arCars = [];

        $rsCars = \CIBlockElement::GetList([], [
            'ACTIVE' => 'Y',
            'IBLOCK_CODE' => $this->arParams['CARS_IBLOCK_CODE'],
            'PROPERTY_COMFORT_CATEGORY_LINK' => $arComfortCategories
        ], false, false, ['NAME', "ID", "PROPERTY_DRIVER_LINK.NAME", "PROPERTY_COMFORT_CATEGORY_LINK.NAME"]);

        while ($arCar = $rsCars->fetch()) {
            $arCars[] = [
                'ID' => $arCar['ID'],
                'CAR_MODEL' => $arCar['NAME'],
                'COMFORT_CATEGORY' => $arCar['PROPERTY_COMFORT_CATEGORY_LINK_NAME'],
                'DRIVER' => [
                    'NAME' => $arCar['PROPERTY_DRIVER_LINK_NAME'],
                ],
            ];
        }

        return $arCars;
    }

    private function checkAvailability(array $arCars): array
    {
        $arFilter = [
            [
                '<=UF_START' => $this->dateEnd,
                '>=UF_END' => $this->dateStart
            ]
        ];

        foreach ($arCars as $arCar) {
            $arFilter['UF_CAR_ID'][] = $arCar['ID'];
        }

        $hlblock = HighloadBlockTable::getList(
            array("filter" => array(
                'TABLE_NAME' => $this->arParams['HL_BLOCK_TABLE']
            ))
        )->fetch();

        if (!$hlblock['ID']) {
            throw new ObjectNotFoundException(Loc::getMessage('CARBOOKING_HLBLOCK_NOT_FOUND'));
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);
        $dataClass = $entity->getDataClass();

        $bookedCars = $dataClass::getList([
            'select' => ['UF_CAR_ID'],
            'filter' => $arFilter,
            'group' => ['UF_CAR_ID'],
        ])->fetchAll();

        $bookedCarIds = array_column($bookedCars, 'UF_CAR_ID');

        return array_filter($arCars, fn($car) => !in_array($car['ID'], $bookedCarIds));
    }
}
