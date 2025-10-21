<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbInfoCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('db:info')
            ->setDescription('Информация о базе данных');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Информация о базе данных');

        try {
            /** @var Connection $connection */
            $connection = Application::getConnection();

            // Получаем информацию о подключении
            $dbType = $connection->getType();
            $dbVersion = $connection->getVersion();
            
            $io->definitionList(
                ['Тип БД' => $dbType],
                ['Версия' => $dbVersion ? implode('.', $dbVersion) : 'N/A']
            );

            // Получаем статистику по таблицам
            if ($dbType === 'mysql') {
                $io->section('Статистика таблиц');
                
                $result = $connection->query("
                    SELECT 
                        COUNT(*) as table_count,
                        SUM(data_length + index_length) as total_size
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE()
                ");
                
                if ($row = $result->fetch()) {
                    $io->writeln(sprintf('Количество таблиц: %d', $row['table_count']));
                    $io->writeln(sprintf('Общий размер: %s', $this->formatBytes((int)$row['total_size'])));
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при получении информации о БД: %s', $e->getMessage()));
            return self::FAILURE;
        }
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

