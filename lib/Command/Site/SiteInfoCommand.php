<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Site;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SiteTable;
use Bitrix\Main\SystemException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SiteInfoCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('site:info')
            ->setDescription('Информация о сайте')
            ->addArgument('site_id', InputArgument::REQUIRED, 'ID сайта');
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $siteId = $input->getArgument('site_id');

        $io->title(sprintf('Информация о сайте: %s', $siteId));

        $site = SiteTable::getById($siteId)->fetch();

        if (!$site) {
            $io->error(sprintf('Сайт с ID "%s" не найден', $siteId));
            return self::FAILURE;
        }

        $io->definitionList(
            ['ID сайта' => $site['LID']],
            ['Название' => $site['NAME']],
            ['Директория' => $site['DIR']],
            ['Домен' => $site['SERVER_NAME'] ?? 'не указан'],
            ['Активен' => $site['ACTIVE'] === 'Y' ? 'Да' : 'Нет'],
            ['Сортировка' => $site['SORT']],
            ['Язык' => $site['LANGUAGE_ID'] ?? 'не указан'],
            ['Email от кого' => $site['EMAIL'] ?? 'не указан']
        );

        return self::SUCCESS;
    }
}

