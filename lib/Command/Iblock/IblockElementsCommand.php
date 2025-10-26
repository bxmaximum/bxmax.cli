<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Iblock;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class IblockElementsCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('iblock:elements')
            ->setDescription('Список элементов инфоблока')
            ->addArgument('id', InputArgument::REQUIRED, 'ID инфоблока')
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Количество элементов',
                100
            )
            ->addOption(
                'active',
                null,
                InputOption::VALUE_NONE,
                'Показать только активные элементы'
            )
            ->addOption(
                'section',
                null,
                InputOption::VALUE_REQUIRED,
                'Фильтр по ID раздела'
            );
    }

    /**
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        if (!Loader::includeModule('iblock')) {
            $io->error('Модуль iblock не установлен');
            return self::FAILURE;
        }

        $iblockId = (int)$input->getArgument('id');
        $limit = (int)$input->getOption('limit');
        $activeOnly = $input->getOption('active');
        $sectionId = $input->getOption('section');
        $verbose = $output->isVerbose();
        $veryVerbose = $output->isVeryVerbose();

        // Проверяем существование инфоблока
        $iblock = IblockTable::getById($iblockId)->fetch();
        if (!$iblock) {
            $io->error(sprintf('Инфоблок с ID=%d не найден', $iblockId));
            return self::FAILURE;
        }

        $io->title(sprintf('Элементы инфоблока: %s (ID: %d)', $iblock['NAME'], $iblockId));

        // Формируем фильтр
        $filter = ['IBLOCK_ID' => $iblockId];
        if ($activeOnly) {
            $filter['ACTIVE'] = 'Y';
        }
        if ($sectionId) {
            $filter['IBLOCK_SECTION_ID'] = (int)$sectionId;
        }

        // Формируем список полей для выборки
        $select = ['ID', 'NAME', 'CODE', 'ACTIVE', 'SORT', 'IBLOCK_SECTION_ID'];
        if ($verbose || $veryVerbose) {
            $select[] = 'DATE_CREATE';
            $select[] = 'TIMESTAMP_X';
            $select[] = 'PREVIEW_PICTURE';
            $select[] = 'DETAIL_PICTURE';
        }

        $result = ElementTable::getList([
            'select' => $select,
            'filter' => $filter,
            'order' => ['SORT' => 'ASC', 'ID' => 'DESC'],
            'limit' => $limit
        ]);

        $tableData = [];
        $count = 0;

        while ($element = $result->fetch()) {
            $row = [
                $element['ID'],
                $element['CODE'] ?? '',
                $element['NAME'],
                $element['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                $element['IBLOCK_SECTION_ID'] ?? '-',
                $element['SORT'],
            ];

            if ($verbose || $veryVerbose) {
                // Подробный режим

                if (isset($element['DATE_CREATE'])) {
                    $row[] = $element['DATE_CREATE']->format('d.m.Y H:i:s');
                } else {
                    $row[] = '-';
                }

                if (isset($element['TIMESTAMP_X'])) {
                    $row[] = $element['TIMESTAMP_X']->format('d.m.Y H:i:s');
                } else {
                    $row[] = '-';
                }

                if ($veryVerbose) {
                    $row[] = !empty($element['PREVIEW_PICTURE']) ? 'Да' : 'Нет';
                    $row[] = !empty($element['DETAIL_PICTURE']) ? 'Да' : 'Нет';
                }
            }

            $tableData[] = $row;
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Элементы не найдены');
            return self::SUCCESS;
        }

        // Формируем заголовки таблицы
        $headers = ['ID', 'Код', 'Название', 'Активен', 'Раздел', 'Сортировка'];

        if ($verbose || $veryVerbose) {
            $headers[] = 'Дата создания';
            $headers[] = 'Дата изменения';

            if ($veryVerbose) {
                $headers[] = 'Превью';
                $headers[] = 'Детально';
            }
        }

        $table = new Table($output);
        $table->setHeaders($headers)
              ->setRows($tableData);
        $table->render();

        $io->success(sprintf('Показано элементов: %d', $count));

        // Выводим информацию о примененных фильтрах
        $filters = [];
        if ($activeOnly) {
            $filters[] = 'только активные';
        }
        if ($sectionId) {
            $filters[] = sprintf('раздел ID=%s', $sectionId);
        }
        if (!empty($filters)) {
            $io->note('Применены фильтры: ' . implode(', ', $filters));
        }

        return self::SUCCESS;
    }
}

