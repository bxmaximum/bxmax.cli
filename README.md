# BXMax CLI - Расширенный набор консольных команд для Bitrix Framework

Модуль предоставляет расширенный набор консольных команд для управления Bitrix Framework, вдохновленный подходами из Laravel и Symfony.

## История изменений

Полный список изменений и версий доступен в [CHANGELOG.md](CHANGELOG.md).

## Установка

1. Установите и настройте composer, а также консоль Битрикса по официальной документации: https://docs.1c-bitrix.ru/pages/get-started/composer.html
2. Скачайте архив релиза с модулем в директорию `/local/modules/bxmax.cli/`
3. Установите модуль через административную панель: "Marketplace - Установленные решения".


## Использование

Все команды запускаются из директории `bitrix`:

```bash
cd /path/to/document_root/bitrix
php bitrix.php [команда] [аргументы] [опции]
```

## Список команд

### 📦 Управление модулями

#### `module:list`
Список всех установленных модулей с версиями и статусом установки
```bash
php bitrix.php module:list
```

#### `module:install <module>`
Установка модуля
```bash
php bitrix.php module:install custom.module
php bitrix.php module:install iblock
```

#### `module:uninstall <module>`
Удаление модуля
```bash
php bitrix.php module:uninstall custom.module
```

#### `module:github <repository>`
Загрузка и установка модуля с GitHub
```bash
php bitrix.php module:github https://github.com/andreyryabin/sprint.migration
```

---

### 💾 Управление кешем

#### `cache:clear`
Полная очистка всех типов кеша (основной кеш, кеш-менеджер, тегированный кеш, композитный кеш)
```bash
php bitrix.php cache:clear
```

#### `cache:type:clear <type>`
Очистка кеша определенного типа
```bash
php bitrix.php cache:type:clear menu
php bitrix.php cache:type:clear iblock
php bitrix.php cache:type:clear catalog
```

---

### 👥 Управление пользователями

#### `user:list`
Список пользователей с возможностью фильтрации
```bash
php bitrix.php user:list
php bitrix.php user:list --limit=50
php bitrix.php user:list --active  # только активные
```

#### `user:create`
Создание нового пользователя
```bash
php bitrix.php user:create \
  --login=john \
  --email=john@example.com \
  --password=SecurePass123 \
  --name=John \
  --last-name=Doe
```

#### `user:password <login>`
Смена пароля пользователя (по логину или email)
```bash
php bitrix.php user:password admin --password=NewPassword123
php bitrix.php user:password user@example.com --password=NewPassword123
```

---

### 📦 Управление инфоблоками

#### `iblock:list`
Список всех инфоблоков с полной информацией
```bash
php bitrix.php iblock:list
```

#### `iblock:elements <id>`
Список элементов инфоблока
```bash
php bitrix.php iblock:elements 1
php bitrix.php iblock:elements 5 --limit=50
```

#### `iblock:facet-rebuild [iblock-id]`
Пересоздание фасетного индекса для инфоблоков (используется для умного фильтра)
```bash
# Список инфоблоков с информацией о фасетном индексе
php bitrix.php iblock:facet-rebuild --list

# Пересоздать индекс для конкретного инфоблока
php bitrix.php iblock:facet-rebuild 1

# Пересоздать индексы для всех инфоблоков с включенным фасетным индексом
php bitrix.php iblock:facet-rebuild
```

---

### 🔍 Управление поиском

#### `search:reindex`
Переиндексация поискового индекса
```bash
# Обычная переиндексация
php bitrix.php search:reindex

# Полная переиндексация (с очисткой индекса)
php bitrix.php search:reindex --full

# Переиндексация с очисткой подсказок
php bitrix.php search:reindex --clear-suggest

# С указанием максимального времени выполнения шага
php bitrix.php search:reindex --max-time=60
```

---

### ⚙️ Управление агентами

#### `agent:list`
Список агентов с информацией о статусе и времени выполнения
```bash
php bitrix.php agent:list
php bitrix.php agent:list --limit=50
php bitrix.php agent:list --active  # только активные
```

#### `agent:run`
Запуск всех активных агентов
```bash
php bitrix.php agent:run
```

---

### 🌐 Управление сайтами

#### `site:list`
Список всех сайтов с информацией о домене и директории
```bash
php bitrix.php site:list
```

#### `site:info <id>`
Подробная информация о сайте
```bash
php bitrix.php site:info s1
php bitrix.php site:info s2
```

---

### 🔧 Отладка и диагностика

#### `debug:config`
Просмотр конфигурации системы
```bash
# Показать основные параметры конфигурации
php bitrix.php debug:config

# Показать конкретный параметр
php bitrix.php debug:config --key=cache
php bitrix.php debug:config --key=connections
php bitrix.php debug:config --key=crypto
```

---

### 💽 База данных

#### `db:info`
Информация о базе данных (тип, версия, количество таблиц, размер)
```bash
php bitrix.php db:info
```

---

## Стандартные команды Битрикс

Помимо команд модуля BXMax CLI, Битрикс предоставляет набор встроенных команд для разработки и обслуживания системы.

### 🛠️ Разработка (dev)

#### `dev:locator-codes <module> [code]`
Генерация файла `.phpstorm.meta.php` для автодополнения Service Locator в PhpStorm
```bash
# Генерация файла метаданных для модуля
php bitrix.php dev:locator-codes main

# С указанием кода
php bitrix.php dev:locator-codes iblock bitrix_iblock_locator_codes
```

Создает файл метаданных `.phpstorm.meta.php`, который помогает PhpStorm распознавать сервисы из Service Locator'а Битрикс.

**Опции:**
- `--show` - выводит содержимое в консоль без сохранения файла (можно использовать с `>` для сохранения в произвольное место)

```bash
# Вывод в консоль
php bitrix.php dev:locator-codes main --show

# Сохранение в произвольный файл
php bitrix.php dev:locator-codes main --show > ./my/custom/path/.phpstorm.meta.php
```

#### `dev:module-skeleton <module> [dir]`
Генерация каркаса (skeleton) нового модуля
```bash
# Создание базового модуля
php bitrix.php dev:module-skeleton my.custom.module

# Создание с поддиректорией в /lib
php bitrix.php dev:module-skeleton my.custom.module Entity
```

Создает базовую структуру модуля со всеми необходимыми файлами и директориями (install/index.php, lib/, lang/ и т.д.).

---

### 🏗️ Создание компонентов и файлов (make)

#### `make:component <name>`
Создание простого компонента с классом и шаблоном
```bash
# Компонент с пространством имен bitrix
php bitrix.php make:component calendar.open-events.list

# Компонент с кастомным пространством имен
php bitrix.php make:component up:calendar.open-events.list

# Компонент в /local вместо /bitrix
php bitrix.php make:component calendar.open-events.list --local

# Компонент без модуля (в /local/components/)
php bitrix.php make:component up:calendar.open-events.list --no-module
```

**Опции:**
- `--module=<module_id>` - указать конкретный модуль
- `--no-module` - создать компонент в папке components вне модуля
- `--local` - создать в директории DOCUMENT_ROOT/local
- `--root=<путь>` - указать корневую папку (по умолчанию DOCUMENT_ROOT)
- `--show` - вывести содержимое в консоль без сохранения

```bash
# Просмотр без сохранения
php bitrix.php make:component my.component --show
```

#### `make:controller <name> [module]`
Создание файла контроллера для REST API
```bash
# Контроллер для модуля
php bitrix.php make:controller Entity partner.module

# С кастомным namespace
php bitrix.php make:controller Entity --namespace My\\Custom\\Namespace

# С генерацией CRUD действий
php bitrix.php make:controller Entity partner.module --actions crud

# С конкретными действиями
php bitrix.php make:controller Entity partner.module --actions index,show,store

# С алиасом (из .settings.php)
php bitrix.php make:controller Entity partner.module --actions crud --alias V2
```

**Опции:**
- `--namespace=<namespace>` или `-ns` - кастомное пространство имен
- `--psr4` / `--no-psr4` - генерировать путь в стиле PSR-4 / camelCase (по умолчанию включено)
- `--root=<путь>` - корневая папка для генерации
- `--show` - вывод в консоль без сохранения
- `--actions=<actions>` - список действий через запятую (используйте `crud` для списка стандартных: list,get,add,update,delete)
- `--alias=<alias>` - алиас контроллера из .settings.php

```bash
# Сохранение в произвольный файл
php bitrix.php make:controller Entity partner.module --show > ./my/folder/my-custom-file.php
```

#### `make:tablet <table_name> [module]`
Создание ORM-класса (DataManager) для таблицы базы данных
```bash
# Создание ORM-класса для таблицы
php bitrix.php make:tablet b_my_custom_table partner.module

# С кастомным namespace
php bitrix.php make:tablet b_my_custom_table --namespace My\\Custom\\Namespace

# С указанием корневой папки
php bitrix.php make:tablet b_my_custom_table partner.module --root ./my/folder
```

**Опции:**
- `--namespace=<namespace>` или `-ns` - кастомное пространство имен
- `--root=<путь>` - корневая папка для генерации (по умолчанию DOCUMENT_ROOT)
- `--psr4` / `--no-psr4` - генерировать путь в стиле PSR-4 / camelCase (по умолчанию включено)
- `--show` - вывод в консоль без сохранения

```bash
# Сохранение в произвольный файл
php bitrix.php make:tablet b_my_table partner.module --show > ./my/folder/MyTable.php
```

---

### 🔄 ORM

#### `orm:annotate [output]`
Сканирование проекта на наличие ORM-сущностей и генерация аннотаций
```bash
# Генерация аннотаций (по умолчанию в bitrix/modules/orm_annotations.php)
php bitrix.php orm:annotate

# С указанием файла для сохранения
php bitrix.php orm:annotate /path/to/orm_annotations.php

# Сканирование конкретных модулей
php bitrix.php orm:annotate --modules=main,iblock,catalog

# Сканирование всех модулей
php bitrix.php orm:annotate --modules=all

# Очистка существующей карты и создание новой
php bitrix.php orm:annotate --clean
```

Анализирует проект, находит все ORM-сущности (классы, наследующие DataManager) и создает файл аннотаций для улучшения автодополнения в IDE. Это системная команда, оптимизирующая построение Entity Relation Map.

**Опции:**
- `--modules=<список>` или `-m` - модули для сканирования через запятую (по умолчанию: main)
- `--clean` или `-c` - очистить текущую карту сущностей перед генерацией

---

### 🌐 Переводы (translate)

#### `translate:index`
Индексация проекта для файлов локализации
```bash
# Индексация модулей (по умолчанию /bitrix/modules)
php bitrix.php translate:index

# Индексация конкретного пути
php bitrix.php translate:index -p /local/modules
php bitrix.php translate:index --path=/bitrix/modules/catalog
```

Сканирует проект и создает поисковый индекс всех языковых файлов для системы переводов Битрикс. Команда индексирует структуру папок lang/, файлы переводов и фразы в них.

**Опции:**
- `--path=<путь>` или `-p` - путь для индексации (по умолчанию: `/bitrix/modules`)

Команда выполняет следующие операции:
1. Сбор информации о папках lang/
2. Индексация структуры путей
3. Индексация языковых файлов
4. Индексация фраз переводов

**Примеры использования:**
```bash
# Индексация всех модулей
php bitrix.php translate:index

# Индексация локальных модулей
php bitrix.php translate:index --path=/local/modules

# С подробным выводом
php bitrix.php translate:index -v
php bitrix.php translate:index -vv  # очень подробный вывод
```

---

### 🔔 Messenger

#### `messenger:consume [queues]`
Запуск обработчика очереди Messenger
```bash
# Запуск worker для всех очередей
php bitrix.php messenger:consume

# Для конкретных очередей
php bitrix.php messenger:consume queue1 queue2

# С ограничением времени работы (1 час)
php bitrix.php messenger:consume --time-limit=3600

# С настройкой задержки между итерациями (по умолчанию 1 секунда)
php bitrix.php messenger:consume --sleep=5
```

Запускает worker для обработки сообщений из очереди Битрикс Messenger. Команда работает в фоновом режиме и обрабатывает асинхронные задачи. 

**Важно:** Для работы команды необходимо настроить в `.settings.php` параметр `messenger.run_mode = 'cli'`.

**Опции:**
- `queues` - названия очередей для обработки (необязательно)
- `--time-limit=<секунды>` или `-t` - ограничение времени работы worker'а
- `--sleep=<секунды>` - время ожидания перед запросом новых сообщений после итерации (по умолчанию: 1)

**Пример конфигурации в .settings.php:**
```php
'messenger' => [
    'value' => [
        'run_mode' => 'cli',
    ],
],
```

**Пример для systemd service:**
```ini
# /etc/systemd/system/bitrix-messenger.service
[Unit]
Description=Bitrix Messenger Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/bitrix
ExecStart=/usr/bin/php bitrix.php messenger:consume --time-limit=3600
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

---

### 🔄 Обновления (update)

#### `update:languages`
Обновление языковых файлов
```bash
# Обновление всех языковых файлов
php bitrix.php update:languages

# Обновление конкретных языков
php bitrix.php update:languages -l ru
php bitrix.php update:languages --languages=ru,en,de
```

Проверяет наличие обновлений языковых файлов и предлагает их установить. Загружает и устанавливает актуальные языковые пакеты из репозитория обновлений Битрикс.

**Опции:**
- `--languages=<коды>` или `-l` - список кодов языков через запятую

#### `update:modules`
Обновление модулей
```bash
# Обновление всех модулей
php bitrix.php update:modules

# Обновление конкретных модулей
php bitrix.php update:modules -m main
php bitrix.php update:modules --modules=main,iblock,catalog

# Импорт версий модулей из JSON (экспертный режим)
php bitrix.php update:modules -i updates.json
php bitrix.php update:modules --import=updates.json
```

Проверяет наличие обновлений для всех или указанных модулей, показывает список доступных обновлений и предлагает их установить. При установке автоматически проверяет и устанавливает зависимости.

**Опции:**
- `--modules=<список>` или `-m` - список модулей через запятую
- `--import=<файл>` или `-i` - JSON-файл с версиями модулей (экспортированный в экспертном режиме)

**Экспертный режим:**
Позволяет установить конкретные версии модулей из JSON-файла. Формат файла:
```json
{
  "main": "23.0.0",
  "iblock": "21.0.0",
  "catalog": "23.5.0"
}
```

#### `update:versions <versions>`
Обновление версий модулей из JSON-файла
```bash
# Обновление версий из файла
php bitrix.php update:versions versions.json
```

Понижает версии модулей в базе данных согласно файлу JSON. Используется для отката версий модулей без переустановки (например, при откате изменений). 

**Аргументы:**
- `versions` - путь к JSON-файлу с версиями модулей

**Формат JSON-файла:**
```json
{
  "main": "22.0.0",
  "iblock": "20.5.0"
}
```

---

### 🛠️ Утилиты

#### `completion <shell>`
Генерация скрипта автодополнения для командной оболочки
```bash
# Для bash
php bitrix.php completion bash > /etc/bash_completion.d/bitrix

# Для zsh
php bitrix.php completion zsh > ~/.zsh/completion/_bitrix

# Для fish
php bitrix.php completion fish > ~/.config/fish/completions/bitrix.fish
```

После установки автодополнения вы сможете использовать Tab для автодополнения команд и их параметров в терминале.

---

## Автодополнение команд

Для просмотра всех доступных команд:
```bash
php bitrix.php list
```

Для справки по конкретной команде:
```bash
php bitrix.php help cache:clear
php bitrix.php help iblock:facet-rebuild
php bitrix.php help search:reindex
```

## Примеры использования в cron

```bash
# Ежедневная очистка кеша в 3:00
0 3 * * * cd /var/www/bitrix && php bitrix.php cache:clear

# Запуск агентов каждые 5 минут
*/5 * * * * cd /var/www/bitrix && php bitrix.php agent:run

# Переиндексация поиска раз в неделю
0 2 * * 0 cd /var/www/bitrix && php bitrix.php search:reindex --full

# Пересоздание фасетных индексов каждую ночь
0 4 * * * cd /var/www/bitrix && php bitrix.php iblock:facet-rebuild
```

## Требования

- Bitrix Framework 25.0+
- PHP 8.1+
- Symfony Console Component (входит в Bitrix)

## Обратная связь и вклад в проект

### Предложения и идеи

Если у вас есть идеи для новых команд или улучшений, пожалуйста:
- Создайте issue в [разделе Issues](https://github.com/yourusername/bxmax.cli/issues) на GitHub
- Опишите вашу идею максимально детально
- Приведите примеры использования, если возможно

### Вклад в разработку

Мы приветствуем вклад в развитие проекта! Если вы хотите добавить новую команду или улучшить существующую:

1. Форкните репозиторий
2. Создайте ветку для вашей функции (`git checkout -b feature/amazing-command`)
3. Внесите изменения и добавьте тесты, если возможно
4. Закоммитьте изменения (`git commit -m 'feat: добавлена команда iblock:amazing-command'`)
5. Запушьте в ветку (`git push origin feature/amazing-command`)
6. Создайте [Pull Request](https://github.com/yourusername/bxmax.cli/pulls)

#### Формат коммитов

Пожалуйста, используйте формат [Conventional Commits](https://www.conventionalcommits.org/) с описанием на русском языке:

- `feat: добавлена команда cache:warmup` - новая функциональность
- `fix: исправлена ошибка в команде user:create` - исправление бага
- `docs: обновлена документация по установке` - изменения в документации
- `refactor: оптимизирована команда iblock:list` - рефакторинг кода
- `test: добавлены тесты для модуля Site` - добавление тестов
- `chore: обновлены зависимости` - технические изменения

Пожалуйста, убедитесь, что ваш код соответствует стандартам PSR-12 и включает необходимую документацию.

## Лицензия

MIT


## Автор

Kirill Novozhilov

