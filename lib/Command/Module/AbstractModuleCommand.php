<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Module;

use Symfony\Component\Console\Command\Command;

/**
 * Абстрактный базовый класс для команд работы с модулями
 */
abstract class AbstractModuleCommand extends Command
{
    /**
     * Получить путь к директории модулей
     */
    protected function getModulesPath(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/local/modules';
    }

    /**
     * Получить путь к конкретному модулю
     */
    protected function getModulePath(string $moduleId): string
    {
        return $this->getModulesPath() . '/' . $moduleId;
    }
}

