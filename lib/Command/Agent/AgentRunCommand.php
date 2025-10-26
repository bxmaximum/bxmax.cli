<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Agent;

use Bitrix\Main\Type\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AgentRunCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('agent:run')
            ->setDescription('Запуск всех активных агентов')
            ->addOption('module', null, InputOption::VALUE_REQUIRED, 'Запустить агенты только указанного модуля')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Принудительный запуск всех агентов (игнорировать время следующего запуска)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $module = $input->getOption('module');
        $force = $input->getOption('force');
        $verbosity = $output->getVerbosity();

        $io->title($force ? 'Принудительный запуск агентов' : 'Запуск агентов');

        try {
            @set_time_limit(0);

            // Получаем список агентов для выполнения
            $filter = ['ACTIVE' => 'Y'];
            if ($module) {
                $filter['MODULE_ID'] = $module;
                $io->text(sprintf('Модуль: <comment>%s</comment>', $module));
            }

            if (!$force) {
                $filter['<=NEXT_EXEC'] = DateTime::createFromTimestamp(time());
            }

            $result = \CAgent::GetList(['SORT' => 'ASC'], $filter);
            $agentsToRun = [];
            
            while ($agent = $result->Fetch()) {
                $agentsToRun[] = $agent;
            }

            if (empty($agentsToRun)) {
                $io->warning('Нет агентов для выполнения');
                return self::SUCCESS;
            }

            $totalAgents = count($agentsToRun);
            $io->text(sprintf('Найдено агентов для выполнения: <info>%d</info>', $totalAgents));
            $io->newLine();

            // Выполняем агенты
            $executed = 0;
            $failed = 0;
            $startTime = microtime(true);

            if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                $io->text('Выполнение агентов:');
            }

            foreach ($agentsToRun as $agent) {
                $agentStartTime = microtime(true);
                
                if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                    $agentName = mb_substr($agent['NAME'], 0, 80);
                    if (mb_strlen($agent['NAME']) > 80) {
                        $agentName .= '...';
                    }
                    $io->text(sprintf('  - [%s] %s', $agent['MODULE_ID'] ?? '-', $agentName));
                }

                try {
                    if ($force) {
                        // Принудительный запуск через eval
                        $agentFunction = $agent['NAME'];
                        @eval('$result = ' . $agentFunction . ';');
                        $executed++;
                        
                        if ($verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                            $duration = round((microtime(true) - $agentStartTime) * 1000, 2);
                            $io->text(sprintf('    <info>✓</info> Выполнено за %s мс', $duration));
                        }
                    } else {
                        // Обычный запуск - считаем агенты
                        $executed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                        $io->text(sprintf('    <error>✗</error> Ошибка: %s', $e->getMessage()));
                    }
                }
            }

            // Если не принудительный режим, вызываем стандартный ExecuteAgents
            if (!$force) {
                \CAgent::ExecuteAgents();
            }

            $duration = round(microtime(true) - $startTime, 2);

            $io->newLine();
            $io->success(sprintf(
                'Выполнение завершено за %s сек. Выполнено: %d, Ошибок: %d',
                $duration,
                $executed,
                $failed
            ));

            if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                $countActive = \CAgent::GetList(['ACTIVE' => 'Y'])->SelectedRowsCount();
                $io->text(sprintf('Всего активных агентов в системе: %d', $countActive));
            }

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Критическая ошибка при выполнении агентов: %s', $e->getMessage()));
            
            if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                $io->text($e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
    }
}

