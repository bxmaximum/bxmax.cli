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
    /**
     * Список чувствительных ключей, которые нужно скрывать
     */
    private const SENSITIVE_KEYS = [
        'password',
        'pass',
        'pwd',
        'secret',
        'key',
        'token',
        'api_key',
        'private_key',
        'crypto_key',
    ];

    protected function configure(): void
    {
        $this->setName('debug:config')
            ->setDescription('Показать конфигурацию системы')
            ->addOption('key', 'k', InputOption::VALUE_OPTIONAL, 'Конкретный ключ конфигурации')
            ->addOption('show-sensitive', null, InputOption::VALUE_NONE, 'Показать чувствительные данные (пароли, ключи)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Конфигурация системы');

        $key = $input->getOption('key');
        $showSensitive = $input->getOption('show-sensitive');
        $verbose = $output->isVerbose();
        $config = Configuration::getInstance();

        // Предупреждение о безопасности
        if ($showSensitive) {
            $io->warning([
                'ВНИМАНИЕ! Включен режим отображения чувствительных данных.',
                'Убедитесь, что вы находитесь в безопасном окружении!',
            ]);
            $io->newLine();
        }

        if ($key) {
            $value = $config->get($key);
            
            if ($value === null) {
                $io->warning(sprintf('Ключ "%s" не найден', $key));
                return self::SUCCESS;
            }

            $io->section(sprintf('Конфигурация: %s', $key));
            
            // Скрываем чувствительные данные, если не указана опция --show-sensitive
            if (!$showSensitive) {
                $value = $this->hideSensitiveData($value);
            }
            
            if ($verbose) {
                $this->printVerboseConfig($output, $value);
            } else {
                $output->writeln(print_r($value, true));
            }
        } else {
            $io->writeln('Основные параметры конфигурации:');
            $io->newLine();

            // Показываем основные параметры
            $params = [
                'connections' => 'Подключения к базе данных',
                'cache' => 'Кеширование',
                'crypto' => 'Шифрование',
                'exception_handling' => 'Обработка исключений',
                'session' => 'Настройки сессий',
                'pull' => 'Bitrix24.Pull',
                'messenger' => 'Мессенджер',
            ];

            foreach ($params as $paramKey => $paramName) {
                $value = $config->get($paramKey);
                if ($value !== null) {
                    $io->section($paramName . ' (' . $paramKey . ')');
                    
                    // Скрываем чувствительные данные, если не указана опция --show-sensitive
                    if (!$showSensitive) {
                        $value = $this->hideSensitiveData($value);
                    }
                    
                    if ($verbose) {
                        $this->printVerboseConfig($output, $value);
                    } else {
                        $output->writeln(print_r($value, true));
                    }
                }
            }

            $io->newLine();
            $io->note([
                'Используйте опцию --key для просмотра конкретного параметра',
                'Используйте -v для подробного вывода',
                'Используйте --show-sensitive для отображения чувствительных данных',
            ]);
            
            if (!$showSensitive) {
                $io->text('🔒 Чувствительные данные скрыты для безопасности');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Скрывает чувствительные данные в конфигурации
     */
    private function hideSensitiveData($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($this->isSensitiveKey($key)) {
                    $data[$key] = '********';
                } elseif (is_array($value)) {
                    $data[$key] = $this->hideSensitiveData($value);
                }
            }
        }
        
        return $data;
    }

    /**
     * Проверяет, является ли ключ чувствительным
     */
    private function isSensitiveKey(string $key): bool
    {
        $keyLower = strtolower($key);
        
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($keyLower, $sensitiveKey)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Выводит конфигурацию в подробном формате
     */
    private function printVerboseConfig(OutputInterface $output, $data, int $level = 0): void
    {
        $indent = str_repeat('  ', $level);
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $output->writeln(sprintf('%s<info>%s:</info>', $indent, $key));
                    $this->printVerboseConfig($output, $value, $level + 1);
                } elseif (is_bool($value)) {
                    $valueStr = $value ? '<fg=green>true</>' : '<fg=red>false</>';
                    $output->writeln(sprintf('%s<info>%s:</info> %s', $indent, $key, $valueStr));
                } elseif ($value === null) {
                    $output->writeln(sprintf('%s<info>%s:</info> <fg=yellow>null</>', $indent, $key));
                } elseif ($value === '********') {
                    $output->writeln(sprintf('%s<info>%s:</info> <fg=red>%s</>', $indent, $key, $value));
                } else {
                    $output->writeln(sprintf('%s<info>%s:</info> %s', $indent, $key, $value));
                }
            }
        } else {
            $output->writeln(sprintf('%s%s', $indent, $data));
        }
    }
}

