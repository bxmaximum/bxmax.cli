<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Iblock;

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyIndex\Manager;
use Bitrix\Main\LoaderException;
use Bxmax\Cli\Helper\IblockHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class FacetIndexRebuildCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('iblock:facet-rebuild')
            ->setDescription('Пересоздание фасетного индекса инфоблоков')
            ->addArgument(
                'iblock-id',
                InputArgument::OPTIONAL,
                'ID инфоблока для пересоздания индекса'
            )
            ->addOption(
                'list',
                'l',
                InputOption::VALUE_NONE,
                'Показать список инфоблоков с информацией о фасетном индексе'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Пересоздать индексы для всех инфоблоков с включенным фасетным индексом'
            );
    }

    /**
     * @throws LoaderException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        if (!Loader::includeModule('iblock')) {
            $io->error('Модуль iblock не установлен');
            return self::FAILURE;
        }

        try {
            // Если указана опция --list, показываем список инфоблоков
            if ($input->getOption('list')) {
                return $this->showIblockList($io, $output);
            }

            $iblockId = $input->getArgument('iblock-id');

            if ($iblockId) {
                // Пересоздаём индекс для конкретного инфоблока
                return $this->rebuildSingleIblock($io, (int)$iblockId);
            }

            return $this->rebuildAllIblocks($io);
            // Не указан ни ID, ни --all - пересоздаём все инфоблоки (по умолчанию)
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    /**
     * Показать список инфоблоков с информацией о фасетном индексе
     */
    protected function showIblockList(SymfonyStyle $io, OutputInterface $output): int
    {
        $io->title('Список инфоблоков с фасетным индексом');

        $result = IblockTable::getList([
            'select' => ['ID', 'NAME', 'CODE', 'IBLOCK_TYPE_ID', 'PROPERTY_INDEX'],
            'order' => ['ID' => 'ASC']
        ]);

        $tableData = [];

        while ($iblock = $result->fetch()) {
            // Проверяем статус фасетного индекса
            $facetStatus = 'Нет';
            if ($iblock['PROPERTY_INDEX'] === 'Y') {
                $facetStatus = 'Да (активен)';
            } elseif ($iblock['PROPERTY_INDEX'] === 'I') {
                $facetStatus = 'Требует переиндексации';
            }
            

            $tableData[] = [
                $iblock['ID'],
                $iblock['NAME'],
                $iblock['CODE'] ?? '',
                $iblock['IBLOCK_TYPE_ID'],
                $facetStatus,
            ];
        }

        if (empty($tableData)) {
            $io->warning('Инфоблоки не найдены');
            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Название', 'Код', 'Тип', 'Фасетный индекс'])
              ->setRows($tableData);
        $table->render();

        $io->newLine();
        $io->note([
            'Для включения фасетного индекса запустите команду с указанием ID инфоблока',
            'Значения статуса: Y - активен, I - требует переиндексации, N - отключен'
        ]);

        return self::SUCCESS;
    }

    /**
     * Пересоздать фасетный индекс для одного инфоблока
     */
    protected function rebuildSingleIblock(SymfonyStyle $io, int $iblockId): int
    {
        $io->title(sprintf('Пересоздание фасетного индекса для инфоблока #%d', $iblockId));

        // Проверяем существование инфоблока
        $iblock = IblockTable::getById($iblockId)->fetch();
        if (!$iblock) {
            $io->error(sprintf('Инфоблок с ID=%d не найден', $iblockId));
            return self::FAILURE;
        }

        $io->writeln(sprintf('Инфоблок: %s', $iblock['NAME']));
        $io->writeln(sprintf('Текущий статус индекса: %s', $iblock['PROPERTY_INDEX'] ?? 'N'));

        @set_time_limit(0);
        @ignore_user_abort(true);

        $io->writeln('Удаление старого индекса...');
        Manager::deleteIndex($iblockId);
        Manager::markAsInvalid($iblockId);

        $io->writeln('Создание нового индекса...');
        $startTime = time();
        
        // Создаем индексатор
        $indexer = Manager::createIndexer($iblockId);
        if (!$indexer) {
            $io->error('Не удалось создать индексатор для инфоблока');
            return self::FAILURE;
        }

        // Выполняем индексацию
        $indexer->startIndex();
        $step = 0;
        
        while ($indexer->continueIndex()) {
            $step++;
            if ($step % 100 === 0) {
                $io->writeln(sprintf('  Обработано шагов: %d', $step));
            }
        }
        
        $indexer->endIndex();
        
        $elapsedTime = time() - $startTime;

        // Очищаем кеши
        IblockHelper::clearCache($iblockId);

        $io->success(sprintf(
            'Фасетный индекс успешно пересоздан! Выполнено шагов: %d. Время выполнения: %d сек.',
            $step,
            $elapsedTime
        ));
        
        return self::SUCCESS;
    }

    /**
     * Пересоздать фасетные индексы для всех инфоблоков
     */
    protected function rebuildAllIblocks(SymfonyStyle $io): int
    {
        $io->title('Пересоздание фасетных индексов для всех инфоблоков');

        @set_time_limit(0);
        @ignore_user_abort(true);

        // Получаем все инфоблоки с фасетным индексом
        $result = IblockTable::getList([
            'select' => ['ID', 'NAME', 'CODE', 'PROPERTY_INDEX'],
            'filter' => [
                'PROPERTY_INDEX' => ['Y', 'I']
            ],
            'order' => ['ID' => 'ASC']
        ]);

        $iblocks = [];
        while ($iblock = $result->fetch()) {
            $iblocks[] = $iblock;
        }

        if (empty($iblocks)) {
            $io->warning('Не найдено инфоблоков с фасетным индексом');
            $io->note('Для включения фасетного индекса используйте команду с указанием конкретного ID инфоблока');
            return self::SUCCESS;
        }

        $io->writeln(sprintf('Найдено инфоблоков для обработки: %d', count($iblocks)));
        $io->newLine();

        $processed = 0;
        $errors = 0;
        $startTime = time();

        foreach ($iblocks as $iblock) {
            $io->section(sprintf(
                'Обработка инфоблока #%d: %s (статус: %s)',
                $iblock['ID'],
                $iblock['NAME'],
                $iblock['PROPERTY_INDEX']
            ));

            try {
                $io->writeln('  Удаление старого индекса...');
                Manager::deleteIndex($iblock['ID']);
                Manager::markAsInvalid($iblock['ID']);

                $io->writeln('  Создание нового индекса...');
                
                // Создаем индексатор
                $indexer = Manager::createIndexer($iblock['ID']);
                if (!$indexer) {
                    $io->writeln('  <error>✗ Не удалось создать индексатор</error>');
                    $errors++;
                    continue;
                }

                // Выполняем индексацию
                $indexer->startIndex();
                $step = 0;
                
                while ($indexer->continueIndex()) {
                    $step++;
                }
                
                $indexer->endIndex();

                // Очищаем кеши
                IblockHelper::clearCache($iblock['ID']);

                $io->writeln(sprintf('  <info>✓ Успешно (шагов: %d)</info>', $step));
                $processed++;
            } catch (\Throwable $e) {
                $io->writeln(sprintf('  <error>✗ Ошибка: %s</error>', $e->getMessage()));
                $errors++;
            }

            $io->newLine();
        }

        $elapsedTime = time() - $startTime;

        $io->newLine();
        if ($errors > 0) {
            $io->warning(sprintf(
                'Обработка завершена с ошибками. Успешно: %d, Ошибок: %d. Время выполнения: %d сек.',
                $processed,
                $errors,
                $elapsedTime
            ));
            return self::FAILURE;
        }

        $io->success(sprintf(
            'Все фасетные индексы успешно пересоздаты! Обработано: %d. Время выполнения: %d сек.',
            $processed,
            $elapsedTime
        ));
        return self::SUCCESS;
    }
}

