<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\User;

use Bitrix\Main\UserTable;
use CUser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserPasswordCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('user:password')
            ->setDescription('Смена пароля пользователя')
            ->addArgument('login', InputArgument::REQUIRED, 'Логин или email пользователя')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Новый пароль');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $login = $input->getArgument('login');
        $newPassword = $input->getOption('password');

        $io->title(sprintf('Смена пароля для пользователя: %s', $login));

        if (!$newPassword) {
            $io->error('Необходимо указать новый пароль через опцию --password');
            return self::FAILURE;
        }

        // Поиск пользователя по логину или email
        $user = UserTable::getList([
            'filter' => [
                'LOGIC' => 'OR',
                ['=LOGIN' => $login],
                ['=EMAIL' => $login],
            ],
            'select' => ['ID', 'LOGIN', 'EMAIL'],
            'limit' => 1,
        ])->fetch();

        if (!$user) {
            $io->error(sprintf('Пользователь "%s" не найден', $login));
            return self::FAILURE;
        }

        $userObj = new CUser();
        $result = $userObj->Update($user['ID'], [
            'PASSWORD' => $newPassword,
            'CONFIRM_PASSWORD' => $newPassword,
        ]);

        if ($result) {
            $io->success(sprintf('Пароль успешно изменен для пользователя %s (ID: %s)', $user['LOGIN'], $user['ID']));
            return self::SUCCESS;
        }

        $io->error(sprintf('Ошибка при смене пароля: %s', $userObj->LAST_ERROR));
        return self::FAILURE;
    }
}

