<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Site;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SiteTable;
use Bitrix\Main\SystemException;
use Bxmax\Cli\Helper\SiteHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class SiteListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('site:list')
            ->setDescription('Список сайтов')
            ->addOption('active', null, InputOption::VALUE_NONE, 'Показать только активные сайты');
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Список сайтов');

        $filter = [];
        if ($input->getOption('active')) {
            $filter['=ACTIVE'] = 'Y';
        }

        $select = ['LID', 'NAME', 'DIR', 'ACTIVE', 'SORT', 'SERVER_NAME', 'DEF', 'LANGUAGE_ID', 'EMAIL'];
        
        $result = SiteTable::getList([
            'select' => $select,
            'filter' => $filter,
            'order' => ['SORT' => 'ASC']
        ]);

        $tableData = [];
        $count = 0;
        $isVerbose = $output->isVerbose();

        while ($site = $result->fetch()) {
            $row = [
                $site['LID'],
                $site['NAME'],
                $site['SERVER_NAME'] ?? '',
                $site['DIR'],
                $site['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                $site['DEF'] === 'Y' ? 'Да' : 'Нет',
            ];

            if ($isVerbose) {
                $row[] = $site['LANGUAGE_ID'] ?? '';
                $row[] = $site['EMAIL'] ?? '';
                
                // Получить дополнительные домены
                $domains = SiteHelper::getDomains($site['LID']);
                $row[] = !empty($domains) ? implode(', ', $domains) : '';
            }

            $tableData[] = $row;
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Сайты не найдены');
            return self::SUCCESS;
        }

        $headers = ['ID', 'Название', 'Домен', 'Директория', 'Активен', 'По умолчанию'];
        
        if ($isVerbose) {
            $headers[] = 'Язык';
            $headers[] = 'Email';
            $headers[] = 'Дополнительные домены';
        }

        $table = new Table($output);
        $table->setHeaders($headers)
              ->setRows($tableData);
        $table->render();

        $io->success(sprintf('Всего сайтов: %d', $count));

        return self::SUCCESS;
    }
}

