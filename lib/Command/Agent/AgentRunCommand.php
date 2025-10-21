<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Agent;

use CAgent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AgentRunCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('agent:run')
            ->setDescription('Запуск всех активных агентов');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Запуск всех агентов');

        try {
            @set_time_limit(0);

            $io->writeln('Запуск агентов...');

            CAgent::ExecuteAgents();
            
            $countAfter = CAgent::GetList(arFilter: ['ACTIVE' => 'Y'])->SelectedRowsCount();

            $io->success(sprintf('Агенты запущены! Активных агентов: %d', $countAfter));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при выполнении агентов: %s', $e->getMessage()));
            return self::FAILURE;
        }
    }
}

