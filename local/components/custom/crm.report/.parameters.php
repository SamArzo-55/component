<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arComponentParameters = [
    'PARAMETERS' => [
        'PAGE_SIZE' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('CRM_REPORT_PARAM_PAGE_SIZE'),
            'TYPE' => 'LIST',
            'VALUES' => [10 => 10, 20 => 20, 50 => 50],
            'DEFAULT' => 10,
        ],
        'SET_TITLE' => [
            'PARENT' => 'ADDITIONAL_SETTINGS',
            'NAME' => Loc::getMessage('CRM_REPORT_PARAM_SET_TITLE'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
    ],
];
