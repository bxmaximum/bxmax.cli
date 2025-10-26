<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Backup;

use Bitrix\Main\Config\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupCreateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('backup:create')
            ->setDescription('Создание резервной копии сайта средствами Битрикса')
            ->addOption(
                'skip-kernel',
                null,
                InputOption::VALUE_NONE,
                'Не включать ядро Битрикса в бэкап'
            )
            ->addOption(
                'skip-public',
                null,
                InputOption::VALUE_NONE,
                'Не включать публичную часть в бэкап'
            )
            ->addOption(
                'skip-db',
                null,
                InputOption::VALUE_NONE,
                'Не включать базу данных в бэкап'
            )
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Имя архива бэкапа (без расширения)'
            )
            ->addOption(
                'no-compression',
                null,
                InputOption::VALUE_NONE,
                'Отключить сжатие архива'
            )
            ->addOption(
                'exclude-dir',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Исключить директорию из бэкапа (можно указать несколько раз)'
            )
            ->addOption(
                'exclude-file',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Исключить файл из бэкапа (можно указать несколько раз)'
            )
            ->addOption(
                'exclude-mask',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Исключить по маске (можно указать несколько раз, например: *.log, tmp/*)'
            )
            ->addOption(
                'skip-stat',
                null,
                InputOption::VALUE_NONE,
                'Исключить статистику из дампа базы данных (таблицы b_stat*)'
            )
            ->addOption(
                'skip-search',
                null,
                InputOption::VALUE_NONE,
                'Исключить поисковый индекс из дампа БД (таблицы b_search_*)'
            )
            ->addOption(
                'skip-log',
                null,
                InputOption::VALUE_NONE,
                'Исключить журнал событий из дампа БД (таблица b_event_log)'
            )
            ->addOption(
                'max-file-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Исключить из архива файлы размером более указанного (в КБ, 0 - без ограничения)',
                '0'
            )
            ->addOption(
                'integrity-check',
                null,
                InputOption::VALUE_NONE,
                'Проверить целостность архива после завершения (по умолчанию включено)'
            )
            ->addOption(
                'no-integrity-check',
                null,
                InputOption::VALUE_NONE,
                'Не проверять целостность архива после завершения'
            )
            ->addOption(
                'max-exec-time',
                null,
                InputOption::VALUE_REQUIRED,
                'Длительность шага в секундах (по умолчанию 300)',
                '300'
            )
            ->addOption(
                'max-exec-time-sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'Интервал между шагами в секундах (по умолчанию 3)',
                '3'
            )
            ->addOption(
                'archive-size-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Максимальный размер несжатых данных в одной части архива в МБ (11-2047, по умолчанию 100)',
                '100'
            )
            ->setHelp(
                'Создает полную резервную копию сайта, включая файлы и базу данных' . PHP_EOL .
                    'Использует встроенные средства Битрикса (классы CBackup и CTar)' . PHP_EOL . PHP_EOL .
                    'Примеры использования:' . PHP_EOL .
                    '  php bitrix.php backup:create' . PHP_EOL .
                    '  php bitrix.php backup:create --name=my_backup' . PHP_EOL .
                    '  php bitrix.php backup:create --skip-db' . PHP_EOL .
                    '  php bitrix.php backup:create --skip-kernel' . PHP_EOL .
                    '  php bitrix.php backup:create --skip-stat --skip-search --skip-log' . PHP_EOL .
                    '  php bitrix.php backup:create --max-file-size=10240' . PHP_EOL .
                    '  php bitrix.php backup:create --exclude-dir=/upload/tmp --exclude-dir=/bitrix/cache' . PHP_EOL .
                    '  php bitrix.php backup:create --exclude-file=/bitrix/.settings.php' . PHP_EOL .
                    '  php bitrix.php backup:create --exclude-mask="*.log" --exclude-mask="*.tmp"' . PHP_EOL .
                    '  php bitrix.php backup:create --archive-size-limit=500 --max-exec-time=600'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Определяем необходимые константы (как в /bitrix/admin/dump.php)
            if (!defined('DOCUMENT_ROOT')) {
                define('DOCUMENT_ROOT', rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/'));
            }
            
            if (!defined('BX_ROOT')) {
                define('BX_ROOT', '/bitrix');
            }

            if (!defined('START_EXEC_TIME')) {
                define('START_EXEC_TIME', microtime(true));
            }

            // Определяем вспомогательные функции в глобальном пространстве имён
            // Класс CBackup требует эти функции
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
                        if (defined("NO_TIME")) {
                            return microtime(true) - START_EXEC_TIME < 1;
                        }
                        return microtime(true) - START_EXEC_TIME < IntOption("dump_max_exec_time", 300);
                    }
                ');
            }

            if (!function_exists('workTime')) {
                eval('
                    function workTime() {
                        return microtime(true) - START_EXEC_TIME;
                    }
                ');
            }

            // Подключаем необходимые файлы и классы
            require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/classes/general/backup.php';

            global $DB, $skip_mask_array, $tar;
            
            // Инициализируем глобальную переменную для масок исключений
            $skip_mask_array = [];

            // Получаем опции исключения
            $excludeDirs = $input->getOption('exclude-dir') ?: [];
            $excludeFiles = $input->getOption('exclude-file') ?: [];
            $excludeMasks = $input->getOption('exclude-mask') ?: [];

            // Формируем массив исключений для Битрикса
            // CBackup ожидает простой массив строк (масок путей)
            foreach ($excludeDirs as $dir) {
                // Директории должны начинаться с / для абсолютного пути
                $skip_mask_array[] = '/' . ltrim($dir, '/');
            }

            foreach ($excludeFiles as $file) {
                // Файлы также с абсолютным путем
                $skip_mask_array[] = '/' . ltrim($file, '/');
            }

            foreach ($excludeMasks as $mask) {
                // Маски передаем как есть
                $skip_mask_array[] = $mask;
            }

            $io->title('Создание резервной копии сайта средствами Битрикса');

            @set_time_limit(0);
            @ignore_user_abort(true);

            // Параметры бэкапа
            $skipKernel = $input->getOption('skip-kernel');
            $skipPublic = $input->getOption('skip-public');
            $skipDb = $input->getOption('skip-db');
            $customName = $input->getOption('name');
            $noCompression = $input->getOption('no-compression');
            
            // Новые параметры экспертного режима
            $skipStat = $input->getOption('skip-stat');
            $skipSearch = $input->getOption('skip-search');
            $skipLog = $input->getOption('skip-log');
            $maxFileSize = (int)$input->getOption('max-file-size');
            $noIntegrityCheck = $input->getOption('no-integrity-check');
            $maxExecTime = max((int)$input->getOption('max-exec-time'), 5);
            $maxExecTimeSleep = (int)$input->getOption('max-exec-time-sleep');
            $archiveSizeLimit = (int)$input->getOption('archive-size-limit');
            
            // Валидация archive-size-limit
            if ($archiveSizeLimit > 2047 || $archiveSizeLimit <= 10) {
                $archiveSizeLimit = 100;
            }

            // Определяем директорию для сохранения бэкапов
            $backupDir = DOCUMENT_ROOT . BX_ROOT . '/backup';

            if (!file_exists($backupDir)) {
                $permissions = defined('BX_DIR_PERMISSIONS') ? BX_DIR_PERMISSIONS : 0755;
                if (!mkdir($backupDir, $permissions, true) && !is_dir($backupDir)) {
                    throw new \RuntimeException(sprintf('Ошибка создания директории "%s"', $backupDir));
                }
            }

            if (!file_exists($backupDir . '/index.php')) {
                $f = fopen($backupDir . '/index.php', 'wb');
                fwrite($f, '<head><meta http-equiv="REFRESH" content="0;URL=/bitrix/admin/index.php"></head>');
                fclose($f);
            }

            if (!is_dir($backupDir) || !is_writable($backupDir)) {
                throw new \RuntimeException('Директория для бэкапов недоступна для записи: ' . $backupDir);
            }

            // Устанавливаем параметры для бэкапа
            Option::set('main', 'dump_file_public', $skipPublic ? 0 : 1);
            Option::set('main', 'dump_file_kernel', $skipKernel ? 0 : 1);
            Option::set('main', 'dump_base', ($skipDb || $DB->type !== 'MYSQL') ? 0 : 1);
            Option::set('main', 'dump_use_compression', $noCompression ? 0 : 1);
            
            // Параметры экспертного режима
            Option::set('main', 'dump_max_exec_time', $maxExecTime);
            Option::set('main', 'dump_max_exec_time_sleep', $maxExecTimeSleep);
            Option::set('main', 'dump_archive_size_limit', $archiveSizeLimit * 1024 * 1024);
            Option::set('main', 'dump_max_file_size', $maxFileSize);
            
            // Параметры исключения из БД
            Option::set('main', 'dump_base_skip_stat', $skipStat ? 1 : 0);
            Option::set('main', 'dump_base_skip_search', $skipSearch ? 1 : 0);
            Option::set('main', 'dump_base_skip_log', $skipLog ? 1 : 0);
            
            // Проверка целостности (по умолчанию включена, если не указано обратное)
            $doIntegrityCheck = $noIntegrityCheck ? 0 : 1;
            Option::set('main', 'dump_integrity_check', $doIntegrityCheck);
            
            // Включаем использование масок исключений, если они заданы
            Option::set('main', 'skip_mask', !empty($skip_mask_array) ? 1 : 0);

            // Формируем имя архива
            if ($customName) {
                $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customName) . '_';
            } else {
                $prefix = '';
            }

            $arcName = \CBackup::GetArcName($prefix);
            $useCompression = !$noCompression && function_exists('gzcompress');
            $extension = '.tar' . ($useCompression ? '.gz' : '');
            $fullArcName = $arcName . $extension;

            $io->section('Параметры бэкапа:');
            $params = [
                'Включить ядро: ' . ($skipKernel ? 'Нет' : 'Да'),
                'Включить публичную часть: ' . ($skipPublic ? 'Нет' : 'Да'),
                'Включить БД: ' . ($skipDb ? 'Нет' : 'Да'),
                'Сжатие: ' . ($useCompression ? 'Да' : 'Нет'),
                'Путь к архиву: ' . basename($fullArcName),
            ];

            // Параметры БД
            if (!$skipDb) {
                $dbExclusions = [];
                if ($skipStat) {
                    $dbExclusions[] = 'статистика';
                }

                if ($skipSearch) {
                    $dbExclusions[] = 'поисковый индекс';
                }

                if ($skipLog) {
                    $dbExclusions[] = 'журнал событий';
                }
                
                if (!empty($dbExclusions)) {
                    $params[] = 'Исключено из БД: ' . implode(', ', $dbExclusions);
                }
            }

            // Параметры экспертного режима
            $params[] = 'Проверка целостности: ' . ($doIntegrityCheck ? 'Да' : 'Нет');
            $params[] = 'Длительность шага: ' . $maxExecTime . ' сек, интервал: ' . $maxExecTimeSleep . ' сек';
            $params[] = 'Размер части архива: ' . $archiveSizeLimit . ' МБ';
            
            if ($maxFileSize > 0) {
                $params[] = 'Макс. размер файла: ' . $maxFileSize . ' КБ';
            }

            // Добавляем информацию об исключениях
            if (!empty($excludeDirs)) {
                $params[] = 'Исключены директории: ' . implode(', ', $excludeDirs);
            }
            if (!empty($excludeFiles)) {
                $params[] = 'Исключены файлы: ' . implode(', ', $excludeFiles);
            }
            if (!empty($excludeMasks)) {
                $params[] = 'Исключены маски: ' . implode(', ', $excludeMasks);
            }

            $io->listing($params);

            $steps = [];
            if (!$skipDb && $DB->type === 'MYSQL') {
                $steps[] = 'Создание дампа базы данных';
                $steps[] = 'Упаковка дампа в архив';
            }
            if (!$skipKernel || !$skipPublic) {
                $steps[] = 'Упаковка файлов в архив';
            }

            $currentStep = 0;
            $totalSteps = count($steps);

            $sqlFile = null;

            // Создаём объект tar для всего процесса
            $tar = new \CTar();
            $tar->EncryptKey = '';
            $tar->ArchiveSizeLimit = 0;
            $tar->path = DOCUMENT_ROOT;
            $tar->ReadBlockCurrent = 0;
            $tar->ReadFileSize = 0;
            
            // Открываем архив один раз для всех операций
            if (!$tar->openWrite($fullArcName)) {
                $errors = !empty($tar->err) ? implode(', ', $tar->err) : 'неизвестная ошибка';
                throw new \RuntimeException('Не удалось открыть архив для записи: ' . $fullArcName . '. Ошибка: ' . $errors);
            }
            
            // Добавляем .config.php в самом начале, если существует
            $configFile = DOCUMENT_ROOT . BX_ROOT . '/.config.php';
            if (file_exists($configFile) && $tar->addFile($configFile) === false) {
                $errors = !empty($tar->err) ? implode(', ', $tar->err) : 'неизвестная ошибка';
                throw new \RuntimeException('Ошибка при добавлении .config.php в архив: ' . $errors);
            }

            // Шаг 1: Создание дампа базы данных
            if (!$skipDb && $DB->type === 'MYSQL') {
                $currentStep++;
                $io->writeln(sprintf('[%d/%d] 📦 Создание дампа базы данных...', $currentStep, $totalSteps));

                $sqlFile = $arcName . '.sql';
                $dumpState = [];

                if (!\CBackup::MakeDump($sqlFile, $dumpState)) {
                    throw new \RuntimeException('Не удалось создать дамп базы данных');
                }

                // Ожидаем завершения создания дампа
                while (empty($dumpState['end'])) {
                    if (!\CBackup::MakeDump($sqlFile, $dumpState)) {
                        throw new \RuntimeException('Ошибка при создании дампа базы данных');
                    }

                    if (!empty($dumpState['TableCount'])) {
                        $finishedTables = $dumpState['TableCount'] - count($dumpState['TABLES'] ?? []);
                        $io->write(sprintf(
                            "\r   Обработано таблиц: %d/%d",
                            $finishedTables,
                            $dumpState['TableCount']
                        ));
                    }
                }
                $io->writeln('');
                $io->writeln('   ✓ Дамп базы данных создан');

                // Шаг 2: Упаковка дампа в архив
                $currentStep++;
                $io->writeln(sprintf('[%d/%d] 📦 Упаковка дампа в архив...', $currentStep, $totalSteps));

                // Добавляем дамп БД в уже открытый архив
                while (haveTime()) {
                    $r = $tar->addFile($sqlFile);
                    if ($r === false) {
                        throw new \RuntimeException('Ошибка при добавлении дампа БД в архив: ' . implode(', ', $tar->err ?? []));
                    }
                    if ((int)$tar->ReadBlockCurrent === 0) {
                        break; // файл полностью добавлен
                    }
                }

                $io->writeln('   ✓ Дамп упакован в архив');
            }

            // Шаг 3: Упаковка файлов
            if (!$skipKernel || !$skipPublic) {
                $currentStep++;
                $io->writeln(sprintf('[%d/%d] 📦 Упаковка файлов в архив...', $currentStep, $totalSteps));

                \CBackup::$DOCUMENT_ROOT_SITE = DOCUMENT_ROOT;
                \CBackup::$REAL_DOCUMENT_ROOT_SITE = realpath(DOCUMENT_ROOT);

                // ВАЖНО: CDirRealScan использует глобальную переменную $tar!
                // Сканируем и добавляем файлы в уже открытый архив
                $dirScan = new \CDirRealScan();
                $result = $dirScan->Scan(DOCUMENT_ROOT);
                
                if ($result === false) {
                    throw new \RuntimeException('Ошибка при сканировании файлов: ' . implode(', ', $dirScan->err ?? []));
                }

                $io->writeln(sprintf('   ✓ Упаковано файлов: %d', $dirScan->FileCount));
            }
            
            // Закрываем архив в самом конце
            $tar->close();

            // Удаляем временный файл дампа, если был создан
            if ($sqlFile && file_exists($sqlFile)) {
                unlink($sqlFile);
            }

            // Получаем размер созданного архива
            $arcSize = 0;
            if (file_exists($fullArcName)) {
                $arcSize = filesize($fullArcName);
            }

            $io->success('Резервная копия успешно создана!');
            $io->writeln(sprintf('Размер архива: %s', \CFile::FormatSize($arcSize)));
            $io->writeln(sprintf('Путь: %s', $fullArcName));
            
            $exclusionCount = $this->getExclusionCount($excludeDirs, $excludeFiles, $excludeMasks);
            if ($exclusionCount > 0) {
                $io->writeln(sprintf('Применено правил исключения: %d', $exclusionCount));
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при создании бэкапа: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    /**
     * Получение общего количества правил исключения
     */
    private function getExclusionCount(array $excludeDirs, array $excludeFiles, array $excludeMasks): int
    {
        return count($excludeDirs) + count($excludeFiles) + count($excludeMasks);
    }
}

