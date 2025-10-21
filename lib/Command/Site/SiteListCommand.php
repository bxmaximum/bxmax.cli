<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Site;

use Bitrix\Main\SiteTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class SiteListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('site:list')
            ->setDescription('Список сайтов');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Список сайтов');

        $result = SiteTable::getList([
            'select' => ['LID', 'NAME', 'DIR', 'ACTIVE', 'SORT', 'SERVER_NAME'],
            'order' => ['SORT' => 'ASC']
        ]);

        $tableData = [];
        $count = 0;

        while ($site = $result->fetch()) {
            $tableData[] = [
                $site['LID'],
                $site['NAME'],
                $site['DIR'],
                $site['SERVER_NAME'] ?? '',
                $site['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                $site['SORT'],
            ];
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Сайты не найдены');
            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Название', 'Директория', 'Домен', 'Активен', 'Сортировка'])
              ->setRows($tableData);
        $table->render();

        $io->success(sprintf('Всего сайтов: %d', $count));

        return self::SUCCESS;
    }
}

