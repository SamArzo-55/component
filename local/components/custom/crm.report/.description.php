<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arComponentDescription = [
    'NAME' => Loc::getMessage('CRM_REPORT_NAME'),
    'DESCRIPTION' => Loc::getMessage('CRM_REPORT_DESCRIPTION'),
    'PATH' => [
        'ID' => 'custom',
        'NAME' => Loc::getMessage('CRM_REPORT_GROUP'),
    ],
];
