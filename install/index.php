<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class bxmax_cli extends CModule
{
    public $MODULE_ID = 'bxmax.cli';
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $DIR;

    public function __construct()
    {
        $arModuleVersion = array();
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('BXMAX_CLI_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('BXMAX_CLI_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('BXMAX_CLI_PARTNER_NAME');
        $this->PARTNER_URI = 'https://bxmax.ru/';

        if (stripos(__FILE__, '/local/modules') !== false) {
            $this->DIR = 'local';
        } else {
            $this->DIR = 'bitrix';
        }
    }

    public function DoInstall()
    {
        RegisterModule($this->MODULE_ID);
        return true;
    }

    public function DoUninstall()
    {
        UnRegisterModule($this->MODULE_ID);
        return true;
    }
}

