<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Module;

use Bxmax\Cli\Service\ModuleInstaller;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда установки модуля
 */
class ModuleInstallCommand extends AbstractModuleCommand
{
    private ModuleInstaller $moduleInstaller;

    public function __construct()
    {
        parent::__construct();
        $this->moduleInstaller = new ModuleInstaller();
    }

    protected function configure(): void
    {
        $this
            ->setName('module:install')
            ->setDescription('Установка модуля')
            ->addArgument(
                'module',
                InputArgument::REQUIRED,
                'ID модуля для установки'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $moduleId = $input->getArgument('module');

        $io->title(sprintf('Установка модуля: %s', $moduleId));

        $io->text('Выполнение DoInstall()...');
        $result = $this->moduleInstaller->install($moduleId);

        if ($result['success']) {
            $io->success($result['message']);
            return self::SUCCESS;
        }

        $io->error($result['message']);
        return self::FAILURE;
    }
}

