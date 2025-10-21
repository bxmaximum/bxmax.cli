<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Module;

use Bitrix\Main\ModuleManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда вывода списка модулей
 */
class ModuleListCommand extends AbstractModuleCommand
{
    protected function configure(): void
    {
        $this
            ->setName('module:list')
            ->setDescription('Список установленных модулей');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Список установленных модулей');

        $modules = ModuleManager::getModulesFromDisk();

        if (empty($modules)) {
            $io->warning('Не найдено модулей на диске');
            return self::SUCCESS;
        }

        $tableData = $this->prepareTableData($modules);

        $this->renderTable($output, $tableData);

        $installedCount = $this->countInstalledModules($modules);
        $io->success(sprintf(
            'Всего модулей: %d (установлено: %d)',
            count($modules),
            $installedCount
        ));

        return self::SUCCESS;
    }

    /**
     * Подготавливает данные для таблицы
     *
     * @param array $modules Массив модулей
     * @return array
     */
    private function prepareTableData(array $modules): array
    {
        $tableData = [];
        foreach ($modules as $moduleId => $moduleData) {
            $tableData[] = [
                $moduleId,
                $moduleData['version'] ?? 'N/A',
                $moduleData['versionDate'] ?? 'N/A',
                $moduleData['isInstalled'] ? 'Да' : 'Нет',
            ];
        }

        return $tableData;
    }

    /**
     * Отрисовывает таблицу с данными
     *
     * @param OutputInterface $output
     * @param array $tableData
     */
    private function renderTable(OutputInterface $output, array $tableData): void
    {
        $table = new Table($output);
        $table
            ->setHeaders(['ID модуля', 'Версия', 'Дата версии', 'Установлен'])
            ->setRows($tableData);
        $table->render();
    }

    /**
     * Подсчитывает количество установленных модулей
     *
     * @param array $modules Массив модулей
     * @return int
     */
    private function countInstalledModules(array $modules): int
    {
        return count(array_filter($modules, fn($module) => $module['isInstalled']));
    }
}

