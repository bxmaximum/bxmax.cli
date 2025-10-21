<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Module;

use Bxmax\Cli\Service\ModuleInstaller;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда удаления модуля
 */
class ModuleUninstallCommand extends AbstractModuleCommand
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
            ->setName('module:uninstall')
            ->setDescription('Удаление модуля')
            ->addArgument(
                'module',
                InputArgument::REQUIRED,
                'ID модуля для удаления'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $moduleId = $input->getArgument('module');

        $io->title(sprintf('Удаление модуля: %s', $moduleId));

        if (!$this->moduleInstaller->isInstalled($moduleId)) {
            $io->warning(sprintf('Модуль "%s" не установлен', $moduleId));
            return self::SUCCESS;
        }

        if (!$io->confirm(sprintf('Вы уверены, что хотите удалить модуль "%s"?', $moduleId), false)) {
            $io->note('Операция отменена');
            return self::SUCCESS;
        }

        $io->text('Выполнение DoUninstall()...');
        $result = $this->moduleInstaller->uninstall($moduleId);

        if ($result['success']) {
            $io->success($result['message']);
            return self::SUCCESS;
        }

        $io->error($result['message']);
        return self::FAILURE;
    }
}

