<?php

return [
    'console' => [
        'value' => [
            'commands' => [
                // Module commands
                \Bxmax\Cli\Command\Module\ModuleListCommand::class,
                \Bxmax\Cli\Command\Module\ModuleInstallCommand::class,
                \Bxmax\Cli\Command\Module\ModuleUninstallCommand::class,
                \Bxmax\Cli\Command\Module\ModuleGithubCommand::class,

                // Cache commands
                \Bxmax\Cli\Command\Cache\CacheClearCommand::class,
                \Bxmax\Cli\Command\Cache\CacheTypeClearCommand::class,

                // User commands
                \Bxmax\Cli\Command\User\UserListCommand::class,
                \Bxmax\Cli\Command\User\UserCreateCommand::class,
                \Bxmax\Cli\Command\User\UserPasswordCommand::class,

                // Iblock commands
                \Bxmax\Cli\Command\Iblock\IblockListCommand::class,
                \Bxmax\Cli\Command\Iblock\IblockElementsCommand::class,
                \Bxmax\Cli\Command\Iblock\FacetIndexRebuildCommand::class,

                // Search commands
                \Bxmax\Cli\Command\Search\SearchReindexCommand::class,

                // Agent commands
                \Bxmax\Cli\Command\Agent\AgentListCommand::class,
                \Bxmax\Cli\Command\Agent\AgentRunCommand::class,

                // Site commands
                \Bxmax\Cli\Command\Site\SiteListCommand::class,
                \Bxmax\Cli\Command\Site\SiteInfoCommand::class,

                // Debug commands
                \Bxmax\Cli\Command\Debug\DebugConfigCommand::class,

                // Database commands
                \Bxmax\Cli\Command\Database\DbInfoCommand::class,

                // Backup commands
                \Bxmax\Cli\Command\Backup\BackupCreateCommand::class,
                \Bxmax\Cli\Command\Backup\BackupCleanCommand::class,
                \Bxmax\Cli\Command\Backup\BackupListCommand::class,
                \Bxmax\Cli\Command\Backup\BackupRestoreCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];

