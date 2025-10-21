<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Iblock;

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class IblockListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('iblock:list')
            ->setDescription('Список инфоблоков');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        if (!Loader::includeModule('iblock')) {
            $io->error('Модуль iblock не установлен');
            return self::FAILURE;
        }

        $io->title('Список инфоблоков');

        $result = IblockTable::getList([
            'select' => ['ID', 'NAME', 'CODE', 'IBLOCK_TYPE_ID', 'ACTIVE', 'SORT'],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ]);

        $tableData = [];
        $count = 0;

        while ($iblock = $result->fetch()) {
            $tableData[] = [
                $iblock['ID'],
                $iblock['NAME'],
                $iblock['CODE'] ?? '',
                $iblock['IBLOCK_TYPE_ID'],
                $iblock['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                $iblock['SORT'],
            ];
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Инфоблоки не найдены');
            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Название', 'Код', 'Тип', 'Активен', 'Сортировка'])
              ->setRows($tableData);
        $table->render();

        $io->success(sprintf('Всего инфоблоков: %d', $count));

        return self::SUCCESS;
    }
}

