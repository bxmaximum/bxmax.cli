<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Iblock;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class IblockListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('iblock:list')
            ->setDescription('Список инфоблоков')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Фильтр по типу инфоблока'
            );
    }

    /**
     * @throws ObjectPropertyException
     * @throws LoaderException
     * @throws ArgumentException
     * @throws SystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        if (!Loader::includeModule('iblock')) {
            $io->error('Модуль iblock не установлен');
            return self::FAILURE;
        }

        $type = $input->getOption('type');
        $verbose = $output->isVerbose();
        $veryVerbose = $output->isVeryVerbose();

        $io->title('Список инфоблоков');

        // Формируем фильтр
        $filter = [];
        if ($type) {
            $filter['IBLOCK_TYPE_ID'] = $type;
        }

        // Выбираем поля в зависимости от уровня детализации
        $select = ['ID', 'NAME', 'CODE', 'IBLOCK_TYPE_ID', 'ACTIVE', 'SORT'];
        if ($verbose || $veryVerbose) {
            $select[] = 'TIMESTAMP_X';
        }

        $result = IblockTable::getList([
            'select' => $select,
            'filter' => $filter,
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ]);

        $tableData = [];
        $count = 0;

        while ($iblock = $result->fetch()) {
            // Подсчитываем количество элементов
            $elementsCount = 0;
            $sectionsCount = 0;
            
            if ($verbose || $veryVerbose) {
                $elementsCount = ElementTable::getCount(['IBLOCK_ID' => $iblock['ID']]);
                $sectionsCount = SectionTable::getCount(['IBLOCK_ID' => $iblock['ID']]);
            }

            $row = [
                $iblock['ID'],
                $iblock['CODE'] ?? '',
                $iblock['NAME'],
                $iblock['IBLOCK_TYPE_ID'],
                $iblock['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
            ];

            if (!$verbose && !$veryVerbose) {
                // Базовый режим - добавляем только элементы
                $elementsCount = ElementTable::getCount(['IBLOCK_ID' => $iblock['ID']]);
                $row[] = $elementsCount;
            } else {
                // Подробный режим
                $row[] = $elementsCount;
                $row[] = $sectionsCount;
                $row[] = $iblock['SORT'];
                
                if ($veryVerbose && isset($iblock['TIMESTAMP_X'])) {
                    $row[] = $iblock['TIMESTAMP_X']->format('d.m.Y H:i:s');
                }
            }

            $tableData[] = $row;
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Инфоблоки не найдены');
            return self::SUCCESS;
        }

        // Формируем заголовки таблицы
        $headers = ['ID', 'Код', 'Название', 'Тип', 'Активен', 'Элементов'];

        if ($verbose || $veryVerbose) {
            $headers[] = 'Разделов';
            $headers[] = 'Сортировка';

            if ($veryVerbose) {
                $headers[] = 'Дата изменения';
            }
        }

        $table = new Table($output);
        $table->setHeaders($headers)
              ->setRows($tableData);
        $table->render();

        $io->success(sprintf('Всего инфоблоков: %d', $count));

        if ($type) {
            $io->note(sprintf('Применен фильтр по типу: %s', $type));
        }

        return self::SUCCESS;
    }
}

