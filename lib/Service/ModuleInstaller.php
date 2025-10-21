<?php

declare(strict_types=1);

namespace Bxmax\Cli\Service;

use Bitrix\Main\ModuleManager;

/**
 * Сервис для установки и удаления модулей
 */
class ModuleInstaller
{
    /**
     * Установка модуля
     *
     * @param string $moduleId ID модуля
     * @return array ['success' => bool, 'message' => string]
     */
    public function install(string $moduleId): array
    {
        if (ModuleManager::isModuleInstalled($moduleId)) {
            return [
                'success' => true,
                'message' => sprintf('Модуль "%s" уже установлен', $moduleId),
            ];
        }

        try {
            $moduleObject = \CModule::CreateModuleObject($moduleId);

            if (!$moduleObject) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Не удалось создать объект модуля "%s". Проверьте наличие файла install/index.php',
                        $moduleId
                    ),
                ];
            }

            $moduleObject->DoInstall();

            if (ModuleManager::isModuleInstalled($moduleId)) {
                return [
                    'success' => true,
                    'message' => sprintf('Модуль "%s" успешно установлен', $moduleId),
                ];
            }

            return [
                'success' => false,
                'message' => sprintf('Не удалось установить модуль "%s"', $moduleId),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf('Ошибка при установке модуля: %s', $e->getMessage()),
            ];
        }
    }

    /**
     * Удаление модуля
     *
     * @param string $moduleId ID модуля
     * @return array ['success' => bool, 'message' => string]
     */
    public function uninstall(string $moduleId): array
    {
        if (!ModuleManager::isModuleInstalled($moduleId)) {
            return [
                'success' => true,
                'message' => sprintf('Модуль "%s" не установлен', $moduleId),
            ];
        }

        try {
            $moduleObject = \CModule::CreateModuleObject($moduleId);

            if (!$moduleObject) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Не удалось создать объект модуля "%s". Проверьте наличие файла install/index.php',
                        $moduleId
                    ),
                ];
            }

            $moduleObject->DoUninstall();

            if (!ModuleManager::isModuleInstalled($moduleId)) {
                return [
                    'success' => true,
                    'message' => sprintf('Модуль "%s" успешно удален', $moduleId),
                ];
            }

            return [
                'success' => false,
                'message' => sprintf('Не удалось удалить модуль "%s"', $moduleId),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf('Ошибка при удалении модуля: %s', $e->getMessage()),
            ];
        }
    }

    /**
     * Проверка установлен ли модуль
     */
    public function isInstalled(string $moduleId): bool
    {
        return ModuleManager::isModuleInstalled($moduleId);
    }
}

