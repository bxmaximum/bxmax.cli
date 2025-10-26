<?php

declare(strict_types=1);

namespace Bxmax\Cli\Helper;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SiteDomainTable;
use Bitrix\Main\SystemException;

/**
 * Вспомогательный класс для работы с сайтами
 */
class SiteHelper
{
    /**
     * Получить список доменов сайта
     *
     * @param string $siteId ID сайта
     * @return array Массив доменов
     */
    public static function getDomains(string $siteId): array
    {
        $domains = [];
        
        try {
            $result = SiteDomainTable::getList([
                'select' => ['DOMAIN'],
                'filter' => ['=LID' => $siteId],
                'order' => ['DOMAIN' => 'ASC']
            ]);

            while ($domain = $result->fetch()) {
                $domains[] = $domain['DOMAIN'];
            }
        } catch (ArgumentException | ObjectPropertyException | SystemException $e) {
            // Игнорируем ошибки, если таблица не существует
        }

        return $domains;
    }
}



