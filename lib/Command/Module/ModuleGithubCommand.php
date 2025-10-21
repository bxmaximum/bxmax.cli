<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Module;

use Bxmax\Cli\Service\FileSystemHelper;
use Bxmax\Cli\Service\GithubDownloader;
use Bxmax\Cli\Service\ModuleInstaller;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда загрузки и установки модуля с GitHub
 */
class ModuleGithubCommand extends AbstractModuleCommand
{
    private GithubDownloader $githubDownloader;
    private ModuleInstaller $moduleInstaller;
    private FileSystemHelper $fileSystemHelper;

    public function __construct()
    {
        parent::__construct();
        $this->fileSystemHelper = new FileSystemHelper();
        $this->githubDownloader = new GithubDownloader($this->fileSystemHelper);
        $this->moduleInstaller = new ModuleInstaller();
    }

    protected function configure(): void
    {
        $this
            ->setName('module:github')
            ->setDescription('Загрузка и установка модуля с GitHub')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'URL репозитория GitHub (например, https://github.com/andreyryabin/sprint.migration)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repositoryUrl = $input->getArgument('repository');

        $io->title('Загрузка модуля с GitHub');

        // Парсинг URL репозитория
        $moduleName = $this->githubDownloader->parseModuleName($repositoryUrl);
        if (!$moduleName) {
            $io->error('Некорректный URL репозитория GitHub');
            return self::FAILURE;
        }

        $io->text(sprintf('Репозиторий: %s', $repositoryUrl));
        $io->text(sprintf('Имя модуля: %s', $moduleName));

        // Определяем путь для установки модуля
        $targetPath = $this->getModulePath($moduleName);

        // Проверяем, существует ли уже такая директория
        if (file_exists($targetPath)) {
            $io->warning(sprintf('Директория "%s" уже существует', $targetPath));
            if (!$io->confirm('Удалить существующую директорию и продолжить?', false)) {
                $io->note('Операция отменена');
                return self::SUCCESS;
            }
            $this->fileSystemHelper->removeDirectory($targetPath);
        }

        // Создаем директорию для модулей, если её нет
        $this->fileSystemHelper->ensureDirectoryExists($this->getModulesPath());

        // Загружаем модуль с GitHub
        $io->section('Загрузка файлов модуля...');
        $downloadResult = $this->githubDownloader->download($repositoryUrl, $targetPath);

        if (!$downloadResult['success']) {
            $io->error($downloadResult['message']);
            return self::FAILURE;
        }

        $io->success($downloadResult['message']);
        $io->text(sprintf('Файлы модуля скопированы в %s', $targetPath));

        // Устанавливаем модуль
        $io->section('Установка модуля...');

        if ($this->moduleInstaller->isInstalled($moduleName)) {
            $io->warning(sprintf('Модуль "%s" уже установлен', $moduleName));
            return self::SUCCESS;
        }

        $io->text('Выполнение DoInstall()...');
        $installResult = $this->moduleInstaller->install($moduleName);

        if ($installResult['success']) {
            $io->success($installResult['message']);
            return self::SUCCESS;
        }

        $io->error($installResult['message']);
        return self::FAILURE;
    }
}

