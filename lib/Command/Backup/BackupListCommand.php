<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Backup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('backup:list')
            ->setDescription('Показать список созданных резервных копий')
            ->addOption(
                'sort',
                null,
                InputOption::VALUE_REQUIRED,
                'Сортировка: date (по дате), size (по размеру), name (по имени)',
                'date'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Ограничить количество отображаемых бэкапов',
                '0'
            )
            ->setHelp(
                'Отображает список всех резервных копий в директории /bitrix/backup/' . PHP_EOL . PHP_EOL .
                    'Примеры использования:' . PHP_EOL .
                    '  php bitrix.php backup:list' . PHP_EOL .
                    '  php bitrix.php backup:list --sort=size' . PHP_EOL .
                    '  php bitrix.php backup:list --limit=10'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if (!defined('DOCUMENT_ROOT')) {
                define('DOCUMENT_ROOT', rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/'));
            }

            if (!defined('BX_ROOT')) {
                define('BX_ROOT', '/bitrix');
            }

            $backupDir = DOCUMENT_ROOT . BX_ROOT . '/backup';

            if (!is_dir($backupDir)) {
                $io->warning('Директория с бэкапами не найдена: ' . $backupDir);
                return self::SUCCESS;
            }

            $sortBy = $input->getOption('sort');
            $limit = (int)$input->getOption('limit');

            // Получаем список файлов бэкапов
            $backups = [];
            $files = glob($backupDir . '/*.tar*');

            if (empty($files)) {
                $io->info('Резервные копии не найдены');
                return self::SUCCESS;
            }

            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'date' => filemtime($file),
                ];
            }

            // Сортировка
            switch ($sortBy) {
                case 'size':
                    usort($backups, static fn($a, $b) => $b['size'] <=> $a['size']);
                    break;
                case 'name':
                    usort($backups, static fn($a, $b) => strcmp($a['name'], $b['name']));
                    break;
                case 'date':
                default:
                    usort($backups, static fn($a, $b) => $b['date'] <=> $a['date']);
                    break;
            }

            // Применяем лимит
            if ($limit > 0) {
                $backups = array_slice($backups, 0, $limit);
            }

            $io->title('Список резервных копий');
            $io->writeln(sprintf('Всего найдено: %d', count($backups)));
            $io->writeln(sprintf('Директория: %s', $backupDir));
            $io->newLine();

            // Формируем таблицу
            $rows = [];
            $totalSize = 0;

            foreach ($backups as $backup) {
                $rows[] = [
                    $backup['name'],
                    \CFile::FormatSize($backup['size']),
                    date('Y-m-d H:i:s', $backup['date']),
                ];
                $totalSize += $backup['size'];
            }

            $io->table(
                ['Имя файла', 'Размер', 'Дата создания'],
                $rows
            );

            $io->writeln(sprintf('Общий размер: %s', \CFile::FormatSize($totalSize)));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при получении списка бэкапов: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}

