<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\User;

use Bitrix\Main\UserGroupTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserCreateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('user:create')
            ->setDescription('Создание нового пользователя')
            ->addOption('login', null, InputOption::VALUE_OPTIONAL, 'Логин пользователя')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email пользователя')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Пароль пользователя')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Имя пользователя', '')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Фамилия пользователя', '')
            ->addOption('second-name', null, InputOption::VALUE_OPTIONAL, 'Отчество пользователя', '')
            ->addOption('active', null, InputOption::VALUE_NONE, 'Создать активного пользователя (по умолчанию)')
            ->addOption('inactive', null, InputOption::VALUE_NONE, 'Создать неактивного пользователя')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Добавить пользователя в группу администраторов')
            ->addOption('groups', null, InputOption::VALUE_OPTIONAL, 'Список ID групп через запятую', '');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Создание пользователя');

        // Получение параметров с интерактивным запросом
        $login = $input->getOption('login');
        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $name = $input->getOption('name');
        $lastName = $input->getOption('last-name');
        $secondName = $input->getOption('second-name');

        // Интерактивный режим для обязательных параметров
        $helper = $this->getHelper('question');

        if (!$login) {
            $question = new Question('Введите логин пользователя: ');
            $question->setValidator(function ($answer) {
                if (empty(trim($answer))) {
                    throw new \RuntimeException('Логин не может быть пустым');
                }
                return $answer;
            });
            $login = $helper->ask($input, $output, $question);
        }

        if (!$email) {
            $question = new Question('Введите email пользователя: ');
            $question->setValidator(function ($answer) {
                if (empty(trim($answer))) {
                    throw new \RuntimeException('Email не может быть пустым');
                }
                if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Некорректный формат email');
                }
                return $answer;
            });
            $email = $helper->ask($input, $output, $question);
        }

        if (!$password) {
            $question = new Question('Введите пароль пользователя: ');
            $question->setValidator(function ($answer) {
                if (empty(trim($answer))) {
                    throw new \RuntimeException('Пароль не может быть пустым');
                }
                return $answer;
            });
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        // Интерактивный запрос необязательных полей
        if (!$name && $input->isInteractive()) {
            $question = new Question('Введите имя пользователя (необязательно): ', '');
            $name = $helper->ask($input, $output, $question);
        }

        if (!$lastName && $input->isInteractive()) {
            $question = new Question('Введите фамилию пользователя (необязательно): ', '');
            $lastName = $helper->ask($input, $output, $question);
        }

        if (!$secondName && $input->isInteractive()) {
            $question = new Question('Введите отчество пользователя (необязательно): ', '');
            $secondName = $helper->ask($input, $output, $question);
        }

        // Определение статуса активности
        $active = 'Y';
        if ($input->getOption('inactive')) {
            $active = 'N';
        }

        $user = new \CUser();
        
        $fields = [
            'LOGIN' => $login,
            'EMAIL' => $email,
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
            'NAME' => $name,
            'LAST_NAME' => $lastName,
            'SECOND_NAME' => $secondName,
            'ACTIVE' => $active,
        ];

        $userId = $user->Add($fields);

        if ($userId) {
            // Добавление в группы
            $groups = [];
            
            // Добавление в группу администраторов
            if ($input->getOption('admin')) {
                $groups[] = 1;
            }
            
            // Добавление в указанные группы
            $groupsOption = $input->getOption('groups');
            if ($groupsOption) {
                $groupIds = array_map('trim', explode(',', $groupsOption));
                $groupIds = array_map('intval', $groupIds);
                $groups = array_merge($groups, $groupIds);
            }
            
            // Удаляем дубликаты
            $groups = array_unique($groups);
            
            // Добавляем пользователя в группы
            if (!empty($groups)) {
                foreach ($groups as $groupId) {
                    if ($groupId > 0) {
                        UserGroupTable::add([
                            'USER_ID' => $userId,
                            'GROUP_ID' => $groupId,
                            'DATE_ACTIVE_FROM' => false,
                            'DATE_ACTIVE_TO' => false,
                        ]);
                    }
                }
            }

            $io->success(sprintf('Пользователь успешно создан с ID: %d', $userId));
            
            $definitionList = [
                ['Логин' => $login],
                ['Email' => $email],
                ['Имя' => $name ?: 'не указано'],
                ['Фамилия' => $lastName ?: 'не указано'],
                ['Отчество' => $secondName ?: 'не указано'],
                ['Активен' => $active === 'Y' ? 'Да' : 'Нет'],
            ];
            
            if (!empty($groups)) {
                $definitionList[] = ['Группы (ID)' => implode(', ', $groups)];
                
                if (in_array(1, $groups)) {
                    $io->note('Пользователь добавлен в группу администраторов');
                }
            }
            
            $io->definitionList(...$definitionList);

            return self::SUCCESS;
        }

        $io->error(sprintf('Ошибка при создании пользователя: %s', $user->LAST_ERROR));
        return self::FAILURE;
    }
}

