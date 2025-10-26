<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Backup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupCleanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('backup:clean')
            ->setDescription('Удалить старые резервные копии')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Удалить бэкапы старше указанного количества дней',
                '0'
            )
            ->addOption(
                'keep',
                'k',
                InputOption::VALUE_REQUIRED,
                'Оставить указанное количество последних бэкапов',
                '0'
            )
            ->addOption(
                'pattern',
                'p',
                InputOption::VALUE_REQUIRED,
                'Шаблон имени файла для удаления (например: daily_*, weekly_*)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Пропустить подтверждение (использовать с осторожностью)'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Показать какие файлы будут удалены, но не удалять их'
            )
            ->setHelp(
                'Удаляет старые резервные копии по заданным критериям' . PHP_EOL . PHP_EOL .
                    'Примеры использования:' . PHP_EOL .
                    '  php bitrix.php backup:clean --days=7' . PHP_EOL .
                    '  php bitrix.php backup:clean --keep=5' . PHP_EOL .
                    '  php bitrix.php backup:clean --pattern="daily_*" --days=7' . PHP_EOL .
                    '  php bitrix.php backup:clean --days=30 --dry-run' . PHP_EOL .
                    '  php bitrix.php backup:clean --keep=10 --force'
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

            $days = (int)$input->getOption('days');
            $keep = (int)$input->getOption('keep');
            $pattern = $input->getOption('pattern');
            $force = $input->getOption('force');
            $dryRun = $input->getOption('dry-run');

            if ($days === 0 && $keep === 0) {
                $io->error('Необходимо указать хотя бы один критерий удаления: --days или --keep');
                return self::FAILURE;
            }

            $backupDir = DOCUMENT_ROOT . BX_ROOT . '/backup';

            if (!is_dir($backupDir)) {
                $io->warning('Директория с бэкапами не найдена: ' . $backupDir);
                return self::SUCCESS;
            }

            // Получаем список файлов бэкапов
            $searchPattern = $pattern ? $backupDir . '/' . $pattern : $backupDir . '/*.tar*';
            $files = glob($searchPattern);

            if (empty($files)) {
                $io->info('Резервные копии не найдены');
                return self::SUCCESS;
            }

            // Сортируем по дате изменения (новые первыми)
            usort($files, static function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $toDelete = [];
            $currentTime = time();

            foreach ($files as $index => $file) {
                if (!is_file($file)) {
                    continue;
                }

                $shouldDelete = false;

                // Проверка по количеству дней
                if ($days > 0) {
                    $fileAge = ($currentTime - filemtime($file)) / 86400; // дней
                    if ($fileAge > $days) {
                        $shouldDelete = true;
                    }
                }

                // Проверка по количеству файлов для сохранения
                if ($keep > 0 && $index >= $keep) {
                    $shouldDelete = true;
                }

                if ($shouldDelete) {
                    $toDelete[] = [
                        'path' => $file,
                        'name' => basename($file),
                        'size' => filesize($file),
                        'date' => filemtime($file),
                        'age_days' => floor(($currentTime - filemtime($file)) / 86400),
                    ];
                }
            }

            if (empty($toDelete)) {
                $io->success('Нет бэкапов для удаления по заданным критериям');
                return self::SUCCESS;
            }

            $io->title($dryRun ? 'Предварительный просмотр удаления' : 'Удаление старых резервных копий');

            // Формируем информацию о критериях
            $criteria = [];
            if ($days > 0) {
                $criteria[] = sprintf('Старше %d дней', $days);
            }
            if ($keep > 0) {
                $criteria[] = sprintf('Оставить последние %d', $keep);
            }
            if ($pattern) {
                $criteria[] = sprintf('Шаблон: %s', $pattern);
            }

            $io->writeln('Критерии удаления:');
            $io->listing($criteria);
            $io->newLine();

            // Показываем список файлов для удаления
            $rows = [];
            $totalSize = 0;

            foreach ($toDelete as $backup) {
                $rows[] = [
                    $backup['name'],
                    \CFile::FormatSize($backup['size']),
                    date('Y-m-d H:i:s', $backup['date']),
                    $backup['age_days'] . ' дней',
                ];
                $totalSize += $backup['size'];
            }

            $io->table(
                ['Имя файла', 'Размер', 'Дата создания', 'Возраст'],
                $rows
            );

            $io->writeln(sprintf('Будет удалено файлов: %d', count($toDelete)));
            $io->writeln(sprintf('Будет освобождено места: %s', \CFile::FormatSize($totalSize)));

            if ($dryRun) {
                $io->note('Режим предварительного просмотра: файлы не будут удалены');
                return self::SUCCESS;
            }

            // Запрос подтверждения
            if (!$force) {
                $io->newLine();
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    'Вы уверены, что хотите удалить эти файлы? (yes/no) [no]: ',
                    false
                );

                if (!$helper->ask($input, $output, $question)) {
                    $io->writeln('Операция отменена');
                    return self::SUCCESS;
                }
            }

            // Удаляем файлы
            $deleted = 0;
            $failed = 0;
            $freedSpace = 0;

            $io->section('Удаление файлов');

            foreach ($toDelete as $backup) {
                try {
                    if (unlink($backup['path'])) {
                        $deleted++;
                        $freedSpace += $backup['size'];
                        if ($output->isVerbose()) {
                            $io->writeln(sprintf('✓ Удален: %s', $backup['name']));
                        }
                    } else {
                        $failed++;
                        $io->writeln(sprintf('✗ Не удалось удалить: %s', $backup['name']));
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $io->writeln(sprintf('✗ Ошибка при удалении %s: %s', $backup['name'], $e->getMessage()));
                }
            }

            if ($deleted > 0) {
                $io->success(sprintf(
                    'Удалено файлов: %d (освобождено %s)',
                    $deleted,
                    \CFile::FormatSize($freedSpace)
                ));
            }

            if ($failed > 0) {
                $io->warning(sprintf('Не удалось удалить файлов: %d', $failed));
            }

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при удалении бэкапов: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}

