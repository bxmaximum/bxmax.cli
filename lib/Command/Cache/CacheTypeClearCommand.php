<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Cache;

use Bitrix\Main\Data\Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheTypeClearCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('cache:type:clear')
            ->setDescription('Очистка кеша определенного типа')
            ->addArgument('type', InputArgument::REQUIRED, 'Тип кеша для очистки (menu, iblock, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getArgument('type');

        $io->title(sprintf('Очистка кеша типа: %s', $type));

        try {
            @set_time_limit(0);

            $cache = Cache::createInstance();
            $cacheDir = '/' . $type;
            
            $cache->cleanDir($cacheDir);

            $io->success(sprintf('Кеш типа "%s" успешно очищен!', $type));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при очистке кеша: %s', $e->getMessage()));
            return self::FAILURE;
        }
    }
}

