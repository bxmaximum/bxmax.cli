<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Iblock;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
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
            ->addArgument('iblock_id', InputArgument::REQUIRED, 'ID инфоблока')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Количество элементов', 20);
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

        $iblockId = (int)$input->getArgument('iblock_id');
        $limit = (int)$input->getOption('limit');

        $io->title(sprintf('Элементы инфоблока ID: %d', $iblockId));

        $result = ElementTable::getList([
            'select' => ['ID', 'NAME', 'CODE', 'ACTIVE', 'SORT'],
            'filter' => ['IBLOCK_ID' => $iblockId],
            'order' => ['SORT' => 'ASC', 'ID' => 'DESC'],
            'limit' => $limit
        ]);

        $tableData = [];
        $count = 0;

        while ($element = $result->fetch()) {
            $tableData[] = [
                $element['ID'],
                $element['NAME'],
                $element['CODE'] ?? '',
                $element['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                $element['SORT'],
            ];
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Элементы не найдены');
            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Название', 'Код', 'Активен', 'Сортировка'])
              ->setRows($tableData);
        $table->render();

        $io->success(sprintf('Показано элементов: %d', $count));

        return self::SUCCESS;
    }
}

