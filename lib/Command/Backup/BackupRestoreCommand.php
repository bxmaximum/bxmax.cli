<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Backup;

use Bitrix\Main\Config\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupRestoreCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('backup:restore')
            ->setDescription('Восстановить сайт из резервной копии')
            ->addArgument(
                'archive',
                InputArgument::REQUIRED,
                'Имя архива для восстановления (например: backup_20241028.tar.gz)'
            )
            ->addOption(
                'skip-db',
                null,
                InputOption::VALUE_NONE,
                'Не восстанавливать базу данных'
            )
            ->addOption(
                'skip-files',
                null,
                InputOption::VALUE_NONE,
                'Не восстанавливать файлы'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Пропустить подтверждение (использовать с осторожностью)'
            )
            ->addOption(
                'max-exec-time',
                null,
                InputOption::VALUE_REQUIRED,
                'Длительность шага в секундах (по умолчанию 300)',
                '300'
            )
            ->setHelp(
                'Восстанавливает сайт из резервной копии' . PHP_EOL .
                    '⚠ ВНИМАНИЕ: Эта операция удалит текущие данные!' . PHP_EOL . PHP_EOL .
                    'Примеры использования:' . PHP_EOL .
                    '  php bitrix.php backup:restore backup_20241028.tar.gz' . PHP_EOL .
                    '  php bitrix.php backup:restore backup.tar.gz --skip-db' . PHP_EOL .
                    '  php bitrix.php backup:restore backup.tar.gz --force'
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

            if (!defined('START_EXEC_TIME')) {
                define('START_EXEC_TIME', microtime(true));
            }

            $archiveName = $input->getArgument('archive');
            $skipDb = $input->getOption('skip-db');
            $skipFiles = $input->getOption('skip-files');
            $force = $input->getOption('force');
            $maxExecTime = max((int)$input->getOption('max-exec-time'), 5);

            if ($skipDb && $skipFiles) {
                $io->error('Нельзя пропустить и базу данных, и файлы одновременно');
                return self::FAILURE;
            }

            $backupDir = DOCUMENT_ROOT . BX_ROOT . '/backup';
            $archivePath = $backupDir . '/' . $archiveName;

            // Проверяем существование архива
            if (!file_exists($archivePath)) {
                $io->error(sprintf('Архив не найден: %s', $archivePath));
                $io->writeln('Используйте команду backup:list для просмотра доступных бэкапов');
                return self::FAILURE;
            }

            if (!is_readable($archivePath)) {
                $io->error(sprintf('Архив недоступен для чтения: %s', $archivePath));
                return self::FAILURE;
            }

            $io->title('Восстановление из резервной копии');
            $io->warning([
                '⚠ ВНИМАНИЕ: Эта операция удалит текущие данные!',
                'Убедитесь, что вы создали резервную копию текущего состояния.',
            ]);

            $io->writeln(sprintf('Архив: %s', $archiveName));
            $io->writeln(sprintf('Размер: %s', \CFile::FormatSize(filesize($archivePath))));
            $io->writeln(sprintf('Дата создания: %s', date('Y-m-d H:i:s', filemtime($archivePath))));
            $io->newLine();

            $operations = [];
            if (!$skipFiles) {
                $operations[] = 'Восстановление файлов';
            }
            if (!$skipDb) {
                $operations[] = 'Восстановление базы данных';
            }

            $io->writeln('Будут выполнены операции:');
            $io->listing($operations);

            // Запрос подтверждения
            if (!$force) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    'Вы уверены, что хотите продолжить? (yes/no) [no]: ',
                    false
                );

                if (!$helper->ask($input, $output, $question)) {
                    $io->writeln('Операция отменена');
                    return self::SUCCESS;
                }
            }

            @set_time_limit(0);
            @ignore_user_abort(true);

            // Определяем вспомогательные функции
            if (!function_exists('IntOption')) {
                eval('
                    function IntOption($name, $def = 0) {
                        return \Bitrix\Main\Config\Option::get("main", $name, $def);
                    }
                ');
            }

            if (!function_exists('haveTime')) {
                eval('
                    function haveTime() {
                        return microtime(true) - START_EXEC_TIME < IntOption("dump_max_exec_time", 300);
                    }
                ');
            }

            // Подключаем необходимые классы
            require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/classes/general/backup.php';
            require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/classes/general/tar_gz.php';

            global $DB;

            // Устанавливаем параметры
            Option::set('main', 'dump_max_exec_time', $maxExecTime);

            $io->section('Начало восстановления');

            // Создаём объект для работы с архивом
            $tar = new \CTar();
            $tar->path = DOCUMENT_ROOT;

            if (!$tar->openRead($archivePath)) {
                throw new \RuntimeException('Не удалось открыть архив для чтения');
            }

            $stepCount = 0;
            $totalSteps = (!$skipFiles ? 1 : 0) + (!$skipDb ? 1 : 0);

            // Восстановление файлов
            if (!$skipFiles) {
                $stepCount++;
                $io->writeln(sprintf('[%d/%d] 📦 Восстановление файлов...', $stepCount, $totalSteps));

                while (true) {
                    $result = $tar->extractFile();
                    
                    if ($result === false) {
                        throw new \RuntimeException('Ошибка при извлечении файлов: ' . implode(', ', $tar->err ?? []));
                    }

                    if ($result === 0) {
                        // Архив закончился
                        break;
                    }

                    // Файл успешно извлечен
                    if ($result === true && $tar->header === null && $tar->FileCount % 100 === 0) {
                        $io->write(sprintf("\r   Извлечено файлов: %d", $tar->FileCount));
                    }
                }

                $io->writeln(sprintf("\r   ✓ Извлечено файлов: %d", $tar->FileCount));
            }

            $tar->close();

            // Восстановление базы данных
            if (!$skipDb) {
                $stepCount++;
                $io->writeln(sprintf('[%d/%d] 📦 Восстановление базы данных...', $stepCount, $totalSteps));

                // Ищем SQL файл в директории бэкапов
                $sqlFile = str_replace(['.tar.gz', '.tar'], '.sql', $archivePath);

                if (!file_exists($sqlFile)) {
                    // Пытаемся найти SQL файл с таким же префиксом
                    $prefix = str_replace(['.tar.gz', '.tar'], '', basename($archivePath));
                    $foundSql = glob($backupDir . '/' . $prefix . '*.sql');
                    
                    if (!empty($foundSql)) {
                        $sqlFile = $foundSql[0];
                    } else {
                        $io->warning('SQL дамп не найден, пропускаем восстановление БД');
                        $io->writeln('Возможно, бэкап был создан без базы данных');
                    }
                }

                if (file_exists($sqlFile) && is_readable($sqlFile)) {
                    $io->writeln('   Найден SQL дамп: ' . basename($sqlFile));

                    if ($DB->type !== 'MYSQL') {
                        throw new \RuntimeException('Восстановление БД поддерживается только для MySQL');
                    }

                    // Восстанавливаем базу данных построчно
                    $fp = fopen($sqlFile, 'rb');
                    if (!$fp) {
                        throw new \RuntimeException('Не удалось открыть SQL файл для чтения');
                    }

                    $sqlQuery = '';
                    $lineNumber = 0;
                    $queriesExecuted = 0;

                    $DB->Query('SET FOREIGN_KEY_CHECKS = 0', true);
                    $DB->Query("SET SQL_MODE=''", true);

                    while (!feof($fp)) {
                        if (!haveTime()) {
                            // Прерываем выполнение для следующего шага
                            usleep(100000); // 0.1 секунды
                        }

                        $line = fgets($fp);
                        $lineNumber++;

                        if ($line === false) {
                            break;
                        }

                        // Пропускаем комментарии и пустые строки
                        $line = trim($line);
                        if (empty($line) || str_starts_with($line, '--') || str_starts_with($line, '#')) {
                            continue;
                        }

                        $sqlQuery .= $line . ' ';

                        // Если строка заканчивается на ; - выполняем запрос
                        if (str_ends_with(rtrim($line), ';')) {
                            $sqlQuery = trim($sqlQuery);
                            if (!empty($sqlQuery)) {
                                $result = $DB->Query($sqlQuery, true);
                                if (!$result) {
                                    $error = $DB->db_Error ?? 'Unknown error';
                                    // Игнорируем ошибки дубликатов
                                    if (!str_contains($error, 'Duplicate entry')) {
                                        fclose($fp);
                                        throw new \RuntimeException(
                                            sprintf('Ошибка выполнения SQL на строке %d: %s', $lineNumber, $error)
                                        );
                                    }
                                }
                                $queriesExecuted++;
                                if ($queriesExecuted % 100 === 0) {
                                    $io->write(sprintf("\r   Выполнено запросов: %d", $queriesExecuted));
                                }
                            }
                            $sqlQuery = '';
                        }
                    }

                    $DB->Query('SET FOREIGN_KEY_CHECKS = 1', true);
                    fclose($fp);

                    $io->writeln('');
                    $io->writeln(sprintf('   ✓ База данных восстановлена (выполнено запросов: %d)', $queriesExecuted));
                }
            }

            $io->success('Восстановление из резервной копии завершено успешно!');
            $io->writeln([
                '',
                'Рекомендуется:',
                '  1. Очистить кеш: php bitrix.php cache:clear',
                '  2. Проверить работу сайта',
                '  3. Проверить права доступа к файлам',
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при восстановлении из бэкапа: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}

