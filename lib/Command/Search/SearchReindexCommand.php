<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Search;

use Bitrix\Main\Loader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


class SearchReindexCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('search:reindex')
            ->setDescription('Переиндексация поискового индекса')
            ->addOption(
                'full',
                'f',
                InputOption::VALUE_NONE,
                'Полная переиндексация (очистка индекса перед переиндексацией)'
            )
            ->addOption(
                'clear-suggest',
                's',
                InputOption::VALUE_NONE,
                'Очистить историю/статистику подсказок для строки поиска'
            )
            ->addOption(
                'max-time',
                't',
                InputOption::VALUE_OPTIONAL,
                'Максимальное время выполнения одного шага (в секундах)',
                30
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $io->title('Переиндексация поискового индекса');

            if (!Loader::includeModule('search')) {
                $io->error('Модуль search не установлен');
                return self::FAILURE;
            }
            
            @set_time_limit(0);
            @ignore_user_abort(true);

            $bFull = $input->getOption('full');
            $clearSuggest = $input->getOption('clear-suggest');
            $maxTime = (int)$input->getOption('max-time');

            if ($bFull) {
                $io->note('Включена полная переиндексация - поисковый индекс будет очищен перед началом');
            }

            if ($clearSuggest) {
                $io->note('Включена очистка истории подсказок');
            }

            $io->writeln(sprintf('Максимальное время выполнения шага: %d сек.', $maxTime));
            $io->newLine();

            $progressBar = null;
            $NS = false;
            $step = 0;
            $totalIndexed = 0;
            $startTime = time();

            $io->writeln('Начало переиндексации...');

            // Первый вызов
            $NS = \CSearch::ReIndexAll($bFull, $maxTime, $NS, $clearSuggest);
            $step++;

            // Если результат - массив, значит требуется продолжение
            while (is_array($NS)) {
                $step++;
                
                // Выводим информацию о прогрессе
                if (isset($NS['MODULE'])) {
                    $io->writeln(sprintf(
                        'Шаг %d: Модуль "%s"%s',
                        $step,
                        $NS['MODULE'],
                        isset($NS['ID']) ? sprintf(' (ID: %s)', $NS['ID']) : ''
                    ));
                }

                // Продолжаем индексацию
                $NS = \CSearch::ReIndexAll(false, $maxTime, $NS, false);
                
                // Небольшая пауза между шагами
                usleep(100000); // 0.1 секунды
            }

            // В конце $NS содержит количество проиндексированных элементов
            $totalIndexed = $NS;
            $elapsedTime = time() - $startTime;

            $io->newLine();
            $io->success(sprintf(
                'Переиндексация завершена! Проиндексировано элементов: %d. Время выполнения: %d сек. (%d шагов)',
                $totalIndexed,
                $elapsedTime,
                $step
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при переиндексации: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}

