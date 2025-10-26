<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\User;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserTable;
use Bitrix\Main\UserGroupTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class UserListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('user:list')
            ->setDescription('Список пользователей')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Количество пользователей', 20)
            ->addOption('active', 'a', InputOption::VALUE_NONE, 'Только активные пользователи')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Только администраторы');
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Список пользователей');

        $limit = (int)$input->getOption('limit');
        $activeOnly = $input->getOption('active');
        $adminOnly = $input->getOption('admin');

        $filter = [];
        if ($activeOnly) {
            $filter['ACTIVE'] = 'Y';
        }

        // Если нужны только администраторы, сначала получим их ID
        $adminUserIds = [];
        if ($adminOnly) {
            $adminGroups = UserGroupTable::getList([
                'select' => ['USER_ID'],
                'filter' => ['GROUP_ID' => 1]
            ]);
            
            while ($group = $adminGroups->fetch()) {
                $adminUserIds[] = $group['USER_ID'];
            }
            
            if (empty($adminUserIds)) {
                $io->warning('Администраторы не найдены');
                return self::SUCCESS;
            }
            
            $filter['ID'] = $adminUserIds;
        }

        $result = UserTable::getList([
            'select' => ['ID', 'LOGIN', 'EMAIL', 'NAME', 'LAST_NAME', 'ACTIVE'],
            'filter' => $filter,
            'limit' => $limit,
            'order' => ['ID' => 'DESC']
        ]);

        $tableData = [];
        $count = 0;

        while ($user = $result->fetch()) {
            $tableData[] = [
                $user['ID'],
                $user['LOGIN'],
                $user['EMAIL'] ?? '',
                sprintf('%s %s', $user['NAME'] ?? '', $user['LAST_NAME'] ?? ''),
                $user['ACTIVE'] === 'Y' ? 'Да' : 'Нет'
            ];
            $count++;
        }

        if (empty($tableData)) {
            $io->warning('Пользователи не найдены');
            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Логин', 'Email', 'Имя', 'Активен'])
              ->setRows($tableData);
        $table->render();

        $io->success(sprintf('Показано пользователей: %d', $count));

        return self::SUCCESS;
    }
}

