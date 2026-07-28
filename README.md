# BXMax CLI - Расширенный набор консольных команд для Bitrix Framework

[![Latest Stable Version](https://img.shields.io/packagist/v/bxmaximum/bxmax.cli.svg?style=flat-square)](https://packagist.org/packages/bxmaximum/bxmax.cli)
[![Total Downloads](https://img.shields.io/packagist/dt/bxmaximum/bxmax.cli.svg?style=flat-square)](https://packagist.org/packages/bxmaximum/bxmax.cli)
[![License](https://img.shields.io/packagist/l/bxmaximum/bxmax.cli.svg?style=flat-square)](https://packagist.org/packages/bxmaximum/bxmax.cli)

Модуль предоставляет расширенный набор консольных команд для управления Bitrix Framework, вдохновленный подходами из Laravel и Symfony.

- Packagist: [bxmaximum/bxmax.cli](https://packagist.org/packages/bxmaximum/bxmax.cli)
- Документация: [https://cli.bxmax.ru/](https://cli.bxmax.ru/)
- GitHub: [bxmaximum/bxmax.cli](https://github.com/bxmaximum/bxmax.cli)

## Основные разделы команд

- **💾 Кеш** - очистка и управление кешем
- **📦 Модули** - установка, удаление и управление модулями
- **👥 Пользователи** - создание, управление пользователями
- **📋 Инфоблоки** - работа с инфоблоками и элементами
- **🔍 Поиск** - переиндексация и управление поиском
- **⚙️ Агенты** - запуск и управление агентами
- **🌐 Сайты** - управление сайтами
- **🔧 Отладка** - диагностика и отладка
- **💽 База данных** - работа с базой данных
- **💾 Резервное копирование** - создание бэкапов

## Быстрый старт

### Установка через Composer

Предварительно настройте Composer и консоль Битрикса: https://docs.1c-bitrix.ru/pages/get-started/composer.html

Пример `composer.json` с установкой модуля в `local/modules/`:

```json
{
  "extra": {
    "installer-paths": {
      "local/modules/{$name}/": ["type:bitrix-module"]
    }
  },
  "require": {
    "bxmaximum/bxmax.cli": "^1.1"
  }
}
```

Затем:

```bash
composer require bxmaximum/bxmax.cli
```

После установки подключите модуль в админке: «Marketplace → Установленные решения».

### Установка вручную

1. Скачайте архив релиза с [GitHub Releases](https://github.com/bxmaximum/bxmax.cli/releases) в директорию `/local/modules/bxmax.cli/`
2. Установите модуль через административную панель: «Marketplace → Установленные решения»

### Использование

Все команды запускаются из директории `bitrix`:

```bash
cd /path/to/document_root/bitrix
php bitrix.php [команда] [аргументы] [опции]
```

Для просмотра всех доступных команд:
```bash
php bitrix.php list
```

Для справки по конкретной команде:
```bash
php bitrix.php help [команда]
```

### Примеры основных команд

```bash
# Очистка кеша
php bitrix.php cache:clear

# Список модулей
php bitrix.php module:list

# Список пользователей
php bitrix.php user:list

# Создание резервной копии
php bitrix.php backup:create

# Переиндексация поиска
php bitrix.php search:reindex
```

## Требования

- Bitrix Framework 25.0+
- PHP 8.1+
- Symfony Console Component (входит в Bitrix)
- Composer + `composer/installers` (для установки через Packagist)

## Обратная связь и вклад в проект

Для предложений и обсуждений:
- Создайте issue в [разделе Issues](https://github.com/bxmaximum/bxmax.cli/issues) на GitHub
- Напишите в Telegram: [@bxmax_cli](https://t.me/bxmax_cli)

Мы приветствуем вклад в развитие проекта! Если вы хотите добавить новую команду или улучшить существующую:

1. Форкните репозиторий
2. Создайте ветку для вашей функции (`git checkout -b feature/amazing-command`)
3. Внесите изменения и добавьте тесты, если возможно
4. Закоммитьте изменения с использованием [Conventional Commits](https://www.conventionalcommits.org/)
5. Запушьте в ветку и создайте [Pull Request](https://github.com/bxmaximum/bxmax.cli/pulls)

Ваш код должен соответствовать стандартам PSR-12 и включать необходимую документацию.

## Лицензия

MIT

## Автор

[Kirill Novozhilov](https://t.me/kirk_novozhilov)
