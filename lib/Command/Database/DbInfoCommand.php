<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\SqlQueryException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class DbInfoCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('db:info')
            ->setDescription('Информация о базе данных')
            ->addOption(
                'show-tables',
                null,
                InputOption::VALUE_NONE,
                'Показать список всех таблиц'
            )
            ->addOption(
                'show-size-details',
                null,
                InputOption::VALUE_NONE,
                'Показать размер каждой таблицы'
            );
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
            
            // Основная информация
            $this->showBasicInfo($io, $connection, $dbType, $dbVersion);

            // Статистика по таблицам
            if ($dbType === 'mysql') {
                $this->showStatistics($io, $connection);
                
                // Дополнительная информация при verbose режиме
                if ($output->isVerbose()) {
                    $this->showDetailedInfo($io, $connection);
                }
                
                // Список таблиц
                if ($input->getOption('show-tables') || $input->getOption('show-size-details')) {
                    $this->showTables($io, $connection, $input->getOption('show-size-details'));
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при получении информации о БД: %s', $e->getMessage()));
            return self::FAILURE;
        }
    }

    /**
     * @throws SqlQueryException
     */
    private function showBasicInfo(SymfonyStyle $io, Connection $connection, string $dbType, ?array $dbVersion): void
    {
        $io->section('Основная информация');
        
        $info = [
            ['Тип СУБД', $dbType],
            ['Версия', $dbVersion ? implode('.', $dbVersion) : 'N/A'],
        ];
        
        // Пытаемся получить информацию о хосте и базе данных
        if ($dbType === 'mysql') {
            $result = $connection->query("SELECT DATABASE() as db_name, USER() as user, @@hostname as host");
            if ($row = $result->fetch()) {
                $info[] = ['Хост', $row['host'] ?? 'localhost'];
                $info[] = ['База данных', $row['db_name'] ?? 'N/A'];
                $info[] = ['Пользователь', explode('@', $row['user'])[0] ?? 'N/A'];
            }
        }
        
        $io->table(['Параметр', 'Значение'], $info);
    }

    /**
     * @throws SqlQueryException
     */
    private function showStatistics(SymfonyStyle $io, Connection $connection): void
    {
        $io->section('Статистика');
        
        $result = $connection->query("
            SELECT 
                COUNT(*) as table_count,
                SUM(CASE WHEN table_name LIKE 'b_%' THEN 1 ELSE 0 END) as bitrix_table_count,
                SUM(data_length) as data_size,
                SUM(index_length) as index_size,
                SUM(data_length + index_length) as total_size
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()
        ");
        
        if ($row = $result->fetch()) {
            $stats = [
                ['Всего таблиц', $row['table_count']],
                ['Таблиц Битрикс (b_*)', $row['bitrix_table_count']],
                ['Размер данных', \CFile::FormatSize((int)$row['data_size'])],
                ['Размер индексов', \CFile::FormatSize((int)$row['index_size'])],
                ['Общий размер', \CFile::FormatSize((int)$row['total_size'])],
            ];
            
            $io->table(['Метрика', 'Значение'], $stats);
        }
    }

    private function showDetailedInfo(SymfonyStyle $io, Connection $connection): void
    {
        $io->section('Дополнительная информация');
        
        $queries = [
            'Кодировка БД' => "SELECT @@character_set_database as value",
            'Кодировка подключения' => "SELECT @@character_set_connection as value",
            'Режим SQL' => "SELECT @@sql_mode as value",
            'Максимальный размер пакета' => "SELECT @@max_allowed_packet as value",
        ];
        
        $details = [];
        foreach ($queries as $label => $query) {
            try {
                $result = $connection->query($query);
                if ($row = $result->fetch()) {
                    $value = $row['value'];
                    // Форматируем размер пакета
                    if ($label === 'Максимальный размер пакета') {
                        $value = \CFile::FormatSize((int)$value);
                    }
                    $details[] = [$label, $value];
                }
            } catch (\Throwable $e) {
                $details[] = [$label, 'N/A'];
            }
        }
        
        $io->table(['Параметр', 'Значение'], $details);
    }

    /**
     * @throws SqlQueryException
     */
    private function showTables(SymfonyStyle $io, Connection $connection, bool $showSizeDetails): void
    {
        $io->section('Список таблиц');
        
        if ($showSizeDetails) {
            // Подробная информация с размерами
            $result = $connection->query("
                SELECT 
                    table_name,
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size,
                    ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
            ");
            
            $tables = [];
            while ($row = $result->fetch()) {
                $tables[] = [
                    $row['table_name'],
                    number_format((float)$row['table_rows']),
                    \CFile::FormatSize((int)$row['data_length']),
                    \CFile::FormatSize((int)$row['index_length']),
                    \CFile::FormatSize((int)$row['total_size']),
                ];
            }
            
            $table = new Table($io);
            $table->setHeaders(['Таблица', 'Строк', 'Размер данных', 'Размер индексов', 'Общий размер']);
        } else {
            // Простой список таблиц
            $result = $connection->query("
                SELECT 
                    table_name,
                    table_rows
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE()
                ORDER BY table_name
            ");
            
            $tables = [];
            while ($row = $result->fetch()) {
                $tables[] = [
                    $row['table_name'],
                    number_format((float)$row['table_rows']),
                ];
            }
            
            $table = new Table($io);
            $table->setHeaders(['Таблица', 'Количество строк']);
        }
        $table->setRows($tables);
        $table->render();
    }
}

