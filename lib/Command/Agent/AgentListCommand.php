<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Agent;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class AgentListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('agent:list')
            ->setDescription('Список агентов')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Ограничить количество выводимых агентов')
            ->addOption('active', null, InputOption::VALUE_NONE, 'Показать только активные агенты')
            ->addOption('inactive', null, InputOption::VALUE_NONE, 'Показать только неактивные агенты')
            ->addOption('module', null, InputOption::VALUE_REQUIRED, 'Фильтр по модулю');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Список агентов');

        $limit = $input->getOption('limit') ? (int)$input->getOption('limit') : null;
        $activeOnly = $input->getOption('active');
        $inactiveOnly = $input->getOption('inactive');
        $module = $input->getOption('module');
        $verbosity = $output->getVerbosity();

        // Проверка на конфликтующие опции
        if ($activeOnly && $inactiveOnly) {
            $io->error('Нельзя использовать --active и --inactive одновременно');
            return self::FAILURE;
        }

        $filter = [];
        if ($activeOnly) {
            $filter['ACTIVE'] = 'Y';
        } elseif ($inactiveOnly) {
            $filter['ACTIVE'] = 'N';
        }

        if ($module) {
            $filter['MODULE_ID'] = $module;
        }

        $result = \CAgent::GetList(['NEXT_EXEC' => 'ASC'], $filter);

        $tableData = [];
        $count = 0;

        while ($agent = $result->Fetch()) {
            if ($limit && $count >= $limit) {
                break;
            }

            $nameLength = $verbosity >= OutputInterface::VERBOSITY_VERBOSE ? 100 : 50;
            $name = mb_substr($agent['NAME'], 0, $nameLength) . (mb_strlen($agent['NAME']) > $nameLength ? '...' : '');

            if ($verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                // Очень подробный режим (-vv)
                $tableData[] = [
                    $agent['ID'],
                    $name,
                    $agent['MODULE_ID'] ?? '-',
                    $agent['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                    $agent['NEXT_EXEC'] ?? '-',
                    $agent['AGENT_INTERVAL'] ?? '0',
                    $agent['SORT'] ?? '100',
                    $agent['LAST_EXEC'] ?? '-',
                    $agent['USER_ID'] ?? '-',
                ];
            } elseif ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                // Подробный режим (-v)
                $tableData[] = [
                    $agent['ID'],
                    $name,
                    $agent['MODULE_ID'] ?? '-',
                    $agent['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                    $agent['NEXT_EXEC'] ?? '-',
                    $agent['AGENT_INTERVAL'] ?? '0',
                    $agent['SORT'] ?? '100',
                ];
            } else {
                // Обычный режим
                $tableData[] = [
                    $agent['ID'],
                    $name,
                    $agent['MODULE_ID'] ?? '-',
                    $agent['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                    $agent['NEXT_EXEC'] ?? '-',
                    $agent['AGENT_INTERVAL'] ?? '0',
                ];
            }
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Агенты не найдены');
            return self::SUCCESS;
        }

        $table = new Table($output);
        
        if ($verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $table->setHeaders(['ID', 'Название', 'Модуль', 'Активен', 'След. запуск', 'Интервал (сек)', 'Приоритет', 'Последний запуск', 'Пользователь']);
        } elseif ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
            $table->setHeaders(['ID', 'Название', 'Модуль', 'Активен', 'След. запуск', 'Интервал (сек)', 'Приоритет']);
        } else {
            $table->setHeaders(['ID', 'Название', 'Модуль', 'Активен', 'След. запуск', 'Интервал (сек)']);
        }
        
        $table->setRows($tableData);
        $table->render();

        $io->success(sprintf('Показано агентов: %d', $count));

        return self::SUCCESS;
    }
}

