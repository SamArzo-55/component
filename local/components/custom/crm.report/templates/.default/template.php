<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
/** @var CMain $APPLICATION */
/** @var CBitrixComponent $component */

if (isset($arResult['ERROR'])) {
    ShowError($arResult['ERROR']);

    return;
}

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.filter',
    '',
    [
        'FILTER_ID' => $arResult['GRID_ID'],
        'GRID_ID' => $arResult['GRID_ID'],
        'FILTER' => $arResult['FILTER_FIELDS'],
        'FILTER_PRESETS' => [],
        'ENABLE_LABEL' => true,
        'ENABLE_LIVE_SEARCH' => false,
        'DISABLE_SEARCH' => true,
    ],
    $component
);

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID' => $arResult['GRID_ID'],
        'COLUMNS' => $arResult['COLUMNS'],
        'ROWS' => $arResult['ROWS'],
        'NAV_OBJECT' => $arResult['NAV'],
        'TOTAL_ROWS_COUNT' => $arResult['TOTAL'],
        'PAGE_SIZES' => $arResult['PAGE_SIZES'],
        'SHOW_PAGESIZE' => true,
        'SHOW_TOTAL_COUNTER' => true,
        'AJAX_MODE' => 'Y',
        'AJAX_ID' => CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
        'AJAX_OPTION_JUMP' => 'N',
        'AJAX_OPTION_HISTORY' => 'N',
        'SHOW_ROW_CHECKBOXES' => false,
        'SHOW_CHECK_ALL_CHECKBOXES' => false,
        'SHOW_SELECTED_COUNTER' => false,
        'SHOW_ACTION_PANEL' => false,
    ],
    $component
);
