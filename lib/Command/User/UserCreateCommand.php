<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\User;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserCreateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('user:create')
            ->setDescription('Создание нового пользователя')
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'Логин пользователя')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email пользователя')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Пароль пользователя')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Имя пользователя', '')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Фамилия пользователя', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Создание пользователя');

        $login = $input->getOption('login');
        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $name = $input->getOption('name');
        $lastName = $input->getOption('last-name');

        if (!$login || !$email || !$password) {
            $io->error('Необходимо указать логин, email и пароль');
            return self::FAILURE;
        }

        $user = new \CUser();
        
        $fields = [
            'LOGIN' => $login,
            'EMAIL' => $email,
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
            'NAME' => $name,
            'LAST_NAME' => $lastName,
            'ACTIVE' => 'Y',
        ];

        $userId = $user->Add($fields);

        if ($userId) {
            $io->success(sprintf('Пользователь успешно создан с ID: %d', $userId));
            
            $io->definitionList(
                ['Логин' => $login],
                ['Email' => $email],
                ['Имя' => $name ?: 'не указано'],
                ['Фамилия' => $lastName ?: 'не указано']
            );

            return self::SUCCESS;
        }

        $io->error(sprintf('Ошибка при создании пользователя: %s', $user->LAST_ERROR));
        return self::FAILURE;
    }
}

