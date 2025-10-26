<?php

declare(strict_types=1);

namespace Bxmax\Cli\Helper;

/**
 * Вспомогательный класс для работы с инфоблоками
 */
class IblockHelper
{
    /**
     * Очистить кеш инфоблока и связанных компонентов
     *
     * @param int $iblockId ID инфоблока
     * @return void
     */
    public static function clearCache(int $iblockId): void
    {
        // Очистка кеша умного фильтра
        \CBitrixComponent::clearComponentCache("bitrix:catalog.smart.filter");
        
        // Очистка тегированного кеша инфоблока
        if (class_exists(\CIBlock::class)) {
            \CIBlock::clearIblockTagCache($iblockId);
        }
    }
}



