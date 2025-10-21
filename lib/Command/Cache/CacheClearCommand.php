<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Cache;

use Bitrix\Main\Application;
use Bitrix\Main\Composite\Page;
use Bitrix\Main\Data\Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheClearCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('cache:clear')
            ->setDescription('Очистка всех типов кеша Битрикс');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $io->title('Очистка кеша Битрикс');
            
            @set_time_limit(0);

            // Очистка основного кеша
            $io->writeln('Очистка основного кеша...');
            BXClearCache(true);
            
            // Очистка кеш-менеджера
            $io->writeln('Очистка кеш-менеджера...');
            $GLOBALS["CACHE_MANAGER"]->CleanAll();
            $GLOBALS["stackCacheManager"]->CleanAll();
            
            // Очистка тегированного кеша
            $io->writeln('Очистка тегированного кеша...');
            $taggedCache = Application::getInstance()->getTaggedCache();
            $taggedCache->clearByTag(true);
            
            // Очистка композитного кеша
            $io->writeln('Очистка композитного кеша...');
            $page = Page::getInstance();
            $page->deleteAll();

            $io->success('Кеш успешно очищен!');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при очистке кеша: %s', $e->getMessage()));
            return self::FAILURE;
        }
    }
}

