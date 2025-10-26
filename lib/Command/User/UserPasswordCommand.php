<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\User;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserTable;
use CUser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserPasswordCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('user:password')
            ->setDescription('Смена пароля пользователя')
            ->addArgument('login_or_email', InputArgument::OPTIONAL, 'Логин или email пользователя')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Новый пароль');
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Смена пароля пользователя');

        $helper = $this->getHelper('question');
        
        // Получение логина/email (с интерактивным запросом)
        $loginOrEmail = $input->getArgument('login_or_email');
        
        if (!$loginOrEmail) {
            $question = new Question('Введите логин или email пользователя: ');
            $question->setValidator(function ($answer) {
                if (empty(trim($answer))) {
                    throw new \RuntimeException('Логин или email не может быть пустым');
                }
                return $answer;
            });
            $loginOrEmail = $helper->ask($input, $output, $question);
        }

        // Поиск пользователя по логину или email
        $user = UserTable::getList([
            'filter' => [
                'LOGIC' => 'OR',
                ['=LOGIN' => $loginOrEmail],
                ['=EMAIL' => $loginOrEmail],
            ],
            'select' => ['ID', 'LOGIN', 'EMAIL', 'NAME', 'LAST_NAME'],
            'limit' => 1,
        ])->fetch();

        if (!$user) {
            $io->error(sprintf('Пользователь "%s" не найден', $loginOrEmail));
            $io->note('Проверьте правильность написания логина/email или используйте команду: php bitrix.php user:list');
            return self::FAILURE;
        }

        // Показываем информацию о найденном пользователе
        $io->section('Найден пользователь:');
        $io->definitionList(
            ['ID' => $user['ID']],
            ['Логин' => $user['LOGIN']],
            ['Email' => $user['EMAIL']],
            ['Имя' => sprintf('%s %s', $user['NAME'] ?? '', $user['LAST_NAME'] ?? '')]
        );

        // Получение нового пароля (с интерактивным запросом)
        $newPassword = $input->getOption('password');
        
        if (!$newPassword) {
            if (!$input->isInteractive()) {
                $io->error('Необходимо указать новый пароль через опцию --password в неинтерактивном режиме');
                return self::FAILURE;
            }

            // Первый ввод пароля
            $question = new Question('Введите новый пароль: ');
            $question->setValidator(function ($answer) {
                if (empty(trim($answer))) {
                    throw new \RuntimeException('Пароль не может быть пустым');
                }
                if (strlen($answer) < 6) {
                    throw new \RuntimeException('Пароль должен содержать минимум 6 символов');
                }
                return $answer;
            });
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $newPassword = $helper->ask($input, $output, $question);

            // Подтверждение пароля
            $confirmQuestion = new Question('Подтвердите пароль: ');
            $confirmQuestion->setValidator(function ($answer) use ($newPassword) {
                if ($answer !== $newPassword) {
                    throw new \RuntimeException('Пароли не совпадают');
                }
                return $answer;
            });
            $confirmQuestion->setHidden(true);
            $confirmQuestion->setHiddenFallback(false);
            $helper->ask($input, $output, $confirmQuestion);
        }

        // Смена пароля
        $userObj = new CUser();
        $result = $userObj->Update($user['ID'], [
            'PASSWORD' => $newPassword,
            'CONFIRM_PASSWORD' => $newPassword,
        ]);

        if ($result) {
            $io->success(sprintf(
                'Пароль успешно изменен для пользователя %s (ID: %s)',
                $user['LOGIN'],
                $user['ID']
            ));
            
            $io->note([
                '🔒 Старый пароль больше не действителен',
                '📝 Смена пароля зарегистрирована в системе',
                '✉️ Пользователь не получит уведомление о смене пароля'
            ]);
            
            return self::SUCCESS;
        }

        $io->error(sprintf('Ошибка при смене пароля: %s', $userObj->LAST_ERROR));
        
        // Подсказки по возможным ошибкам
        if (str_contains($userObj->LAST_ERROR, 'пароль') || str_contains($userObj->LAST_ERROR, 'password')) {
            $io->warning([
                'Возможные причины ошибки:',
                '• Пароль не соответствует политике безопасности',
                '• Пароль слишком короткий (минимум 6-8 символов)',
                '• Пароль должен содержать буквы, цифры или спецсимволы',
                '• Проверьте настройки безопасности в админ-панели'
            ]);
        }
        
        return self::FAILURE;
    }
}


