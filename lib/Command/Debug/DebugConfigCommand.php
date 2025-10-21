<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Debug;

use Bitrix\Main\Config\Configuration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DebugConfigCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('debug:config')
            ->setDescription('Показать конфигурацию системы')
            ->addOption('key', 'k', InputOption::VALUE_OPTIONAL, 'Конкретный ключ конфигурации');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Конфигурация системы');

        $key = $input->getOption('key');
        $config = Configuration::getInstance();

        if ($key) {
            $value = $config->get($key);
            
            if ($value === null) {
                $io->warning(sprintf('Ключ "%s" не найден', $key));
                return self::SUCCESS;
            }

            $io->section(sprintf('Конфигурация: %s', $key));
            $output->writeln(print_r($value, true));
        } else {
            $io->writeln('Основные параметры конфигурации:');
            $io->newLine();

            // Показываем основные параметры
            $params = [
                'cache' => 'Кеш',
                'connections' => 'Подключения',
                'crypto' => 'Криптография',
                'exception_handling' => 'Обработка исключений',
            ];

            foreach ($params as $paramKey => $paramName) {
                $value = $config->get($paramKey);
                if ($value !== null) {
                    $io->section($paramName . ' (' . $paramKey . ')');
                    $output->writeln(print_r($value, true));
                }
            }

            $io->note('Используйте опцию --key для просмотра конкретного параметра');
        }

        return self::SUCCESS;
    }
}

