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
            )
            ->addOption(
                'site',
                null,
                InputOption::VALUE_REQUIRED,
                'Индексировать только указанный сайт (ID сайта)'
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
            $siteId = $input->getOption('site');

            // Проверка существования сайта
            if ($siteId) {
                if (!Loader::includeModule('main')) {
                    $io->error('Модуль main не установлен');
                    return self::FAILURE;
                }
                
                $rsSite = \CSite::GetByID($siteId);
                if (!$rsSite->Fetch()) {
                    $io->error(sprintf('Сайт с ID "%s" не найден', $siteId));
                    return self::FAILURE;
                }
                
                $io->note(sprintf('Индексация только для сайта: %s', $siteId));
            }

            if ($bFull) {
                $io->note('Включена полная переиндексация - поисковый индекс будет очищен перед началом');
            }

            if ($clearSuggest) {
                $io->note('Включена очистка истории подсказок');
            }

            $io->writeln(sprintf('Максимальное время выполнения шага: %d сек.', $maxTime));
            $io->newLine();

            $NS = [];
            $step = 0;
            $startTime = time();
            $moduleStats = [];
            $lastModule = null;
            $stuckWarningShown = false;

            // Предупреждение о слишком маленьком времени
            if ($maxTime < 5) {
                $io->warning(sprintf(
                    'Установлено очень маленькое время выполнения шага (%d сек). ' .
                    'Это может привести к зависанию на больших модулях. ' .
                    'Рекомендуется использовать значение не менее 10 секунд.',
                    $maxTime
                ));
            }

            $io->writeln('Начало переиндексации...');
            $io->newLine();

            // Первый вызов
            $NS = \CSearch::ReIndexAll($bFull, $maxTime, $NS, $clearSuggest);
            $step++;
            $lastStepTime = time();

            // Если результат - массив, значит требуется продолжение
            while (is_array($NS)) {
                $step++;
                
                // Выводим информацию о прогрессе
                if (isset($NS['MODULE'])) {
                    $currentModule = $NS['MODULE'];
                    
                    // Если началась обработка нового модуля
                    if ($currentModule !== $lastModule) {
                        if ($lastModule !== null && $output->isVerbose()) {
                            $io->writeln(sprintf(
                                '  ✓ Модуль "%s" обработан',
                                $lastModule
                            ));
                        }
                        
                        $io->writeln(sprintf(
                            '<info>▶ Модуль:</info> %s',
                            $currentModule
                        ));
                        
                        $lastModule = $currentModule;
                        $stuckWarningShown = false; // Сбрасываем флаг для нового модуля
                        
                        if (!isset($moduleStats[$currentModule])) {
                            $moduleStats[$currentModule] = 0;
                        }
                    }
                    
                    // Подробный вывод на высоком уровне verbose
                    if (isset($NS['ID']) && $output->isVeryVerbose()) {
                        $io->writeln(sprintf(
                            '  • Шаг %d: ID=%s',
                            $step,
                            $NS['ID']
                        ));
                    }
                    
                    $moduleStats[$currentModule]++;
                }

                // Проверка на зависание
                $timeSinceLastStep = time() - $lastStepTime;
                if ($timeSinceLastStep > ($maxTime * 3) && !$stuckWarningShown && !$output->isVerbose()) {
                    $io->writeln(sprintf(
                        '  <comment>⏳ Обработка модуля "%s" занимает больше времени... (уже %d сек)</comment>',
                        $lastModule ?? 'unknown',
                        $timeSinceLastStep
                    ));
                    $stuckWarningShown = true;
                }

                // Продолжаем индексацию
                $NS = \CSearch::ReIndexAll(false, $maxTime, $NS);
                $lastStepTime = time();
                
                // Показываем прогресс (точку) в не-verbose режиме
                if ($step % 10 === 0 && !$output->isVerbose()) {
                    $output->write('.');
                }
                
                // Небольшая пауза между шагами
                usleep(100000); // 0.1 секунды
            }

            // Закрываем информацию о последнем модуле
            if ($lastModule !== null && $output->isVerbose()) {
                $io->writeln(sprintf(
                    '  ✓ Модуль "%s" обработан',
                    $lastModule
                ));
            }

            // Переносим строку если были точки прогресса
            if ($step > 10 && !$output->isVerbose()) {
                $io->newLine();
            }

            // В конце $NS содержит количество проиндексированных элементов
            $totalIndexed = $NS;
            $elapsedTime = time() - $startTime;
            $elapsedMinutes = floor($elapsedTime / 60);
            $elapsedSeconds = $elapsedTime % 60;

            $io->newLine();
            
            // Выводим статистику по модулям, если запрошен verbose режим
            if (!empty($moduleStats) && $output->isVerbose()) {
                $io->section('Статистика по модулям');
                
                $tableData = [];
                foreach ($moduleStats as $module => $steps) {
                    $tableData[] = [$module, $steps];
                }
                
                $io->table(
                    ['Модуль', 'Шагов обработки'],
                    $tableData
                );
            }

            // Итоговое сообщение
            $timeStr = $elapsedMinutes > 0 
                ? sprintf('%d мин %d сек', $elapsedMinutes, $elapsedSeconds)
                : sprintf('%d сек', $elapsedSeconds);

            $io->success(sprintf(
                'Переиндексация завершена! Проиндексировано элементов: %d. Время выполнения: %s (%d шагов)',
                $totalIndexed,
                $timeStr,
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

