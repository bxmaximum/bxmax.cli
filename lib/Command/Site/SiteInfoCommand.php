<?php

declare(strict_types=1);

namespace Bxmax\Cli\Command\Site;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SiteTable;
use Bitrix\Main\SystemException;
use Bxmax\Cli\Helper\SiteHelper;
use CSite;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SiteInfoCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('site:info')
            ->setDescription('Информация о сайте')
            ->addArgument('site_id', InputArgument::REQUIRED, 'ID сайта');
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $siteId = $input->getArgument('site_id');

        $io->title(sprintf('Информация о сайте: %s', $siteId));

        $site = SiteTable::getById($siteId)->fetch();

        if (!$site) {
            $io->error(sprintf('Сайт с ID "%s" не найден', $siteId));
            return self::FAILURE;
        }

        // Основная информация
        $io->section('Основная информация');
        $io->definitionList(
            ['ID сайта' => $site['LID']],
            ['Название' => $site['NAME']],
            ['Активен' => $site['ACTIVE'] === 'Y' ? 'Да' : 'Нет'],
            ['По умолчанию' => $site['DEF'] === 'Y' ? 'Да' : 'Нет'],
            ['Сортировка' => $site['SORT']]
        );

        // Домены
        $io->section('Домены');
        $domains = SiteHelper::getDomains($siteId);
        $io->definitionList(
            ['Основной домен' => $site['SERVER_NAME'] ?? 'не указан'],
            ['Все домены' => !empty($domains) ? implode(', ', $domains) : 'не указаны']
        );

        // Пути
        $io->section('Пути');
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $absolutePath = $documentRoot . $site['DIR'];
        $io->definitionList(
            ['Корневая директория' => $site['DIR']],
            ['Абсолютный путь' => $absolutePath],
            ['Существует' => is_dir($absolutePath) ? 'Да' : 'Нет']
        );

        // Локализация
        $io->section('Локализация');
        $io->definitionList(
            ['Язык интерфейса' => $site['LANGUAGE_ID'] ?? 'не указан'],
            ['Кодировка' => $site['CHARSET'] ?? 'не указана'],
            ['Направление письма' => $site['DIRECTION'] ?? 'LTR'],
            ['Формат даты' => $site['FORMAT_DATE'] ?? 'не указан'],
            ['Формат даты и времени' => $site['FORMAT_DATETIME'] ?? 'не указан'],
            ['Название формата даты' => $site['FORMAT_NAME'] ?? 'не указано']
        );

        // Контакты
        $io->section('Контакты');
        $io->definitionList(
            ['Email администратора' => $site['EMAIL'] ?? 'не указан'],
            ['Имя отправителя' => $site['NAME'] ?? 'не указано']
        );

        // Настройки
        $io->section('Настройки');
        $defaultTemplate = $this->getDefaultTemplate($siteId);
        $io->definitionList(
            ['Шаблон по умолчанию' => $defaultTemplate ?: 'не указан'],
            ['Описание' => $site['DESCRIPTION'] ?? 'не указано']
        );

        // Дополнительная информация в подробном режиме
        if ($output->isVerbose()) {
            $io->section('Дополнительная информация');

            // Показать все поля из базы
            $io->writeln('<comment>Все поля из базы данных:</comment>');
            foreach ($site as $key => $value) {
                if (!in_array($key, ['LID', 'NAME', 'ACTIVE', 'SORT', 'DEF', 'DIR', 'SERVER_NAME', 
                    'LANGUAGE_ID', 'CHARSET', 'DIRECTION', 'FORMAT_DATE', 'FORMAT_DATETIME', 
                    'FORMAT_NAME', 'EMAIL', 'DESCRIPTION'])) {
                    $io->writeln(sprintf('  <info>%s:</info> %s', $key, $value ?? 'NULL'));
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Получить шаблон сайта по умолчанию
     *
     * @param string $siteId
     * @return string|null
     */
    private function getDefaultTemplate(string $siteId): ?string
    {
        try {
            Loader::includeModule('main');
            
            $site = CSite::GetByID($siteId)->Fetch();
            if ($site && !empty($site['TEMPLATE'])) {
                return is_array($site['TEMPLATE']) ? reset($site['TEMPLATE'])['TEMPLATE'] : $site['TEMPLATE'];
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }

        return null;
    }

}

