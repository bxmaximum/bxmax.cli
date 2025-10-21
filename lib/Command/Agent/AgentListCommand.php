<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Agent;

use Bitrix\Main\Agent\AgentTable;
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
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Количество агентов', 20)
            ->addOption('active', 'a', InputOption::VALUE_NONE, 'Только активные агенты');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Список агентов');

        $limit = (int)$input->getOption('limit');
        $activeOnly = $input->getOption('active');

        $filter = [];
        if ($activeOnly) {
            $filter['ACTIVE'] = 'Y';
        }

        $result = \CAgent::GetList(arFilter: $filter);

        $tableData = [];
        $count = 0;

        while ($agent = $result->Fetch()) {
            $tableData[] = [
                $agent['ID'],
                mb_substr($agent['NAME'], 0, 50) . (mb_strlen($agent['NAME']) > 50 ? '...' : ''),
                $agent['MODULE_ID'] ?? '',
                $agent['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                $agent['NEXT_EXEC'] ?? '',
                $agent['AGENT_INTERVAL'] ?? '',
            ];
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Агенты не найдены');
            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Название', 'Модуль', 'Активен', 'След. запуск', 'Интервал'])
              ->setRows($tableData);
        $table->render();

        $io->success(sprintf('Показано агентов: %d', $count));

        return self::SUCCESS;
    }
}

