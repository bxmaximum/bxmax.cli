<?php

declare(strict_types=1);

namespace Bxmax\Cli\Service;

/**
 * Сервис для загрузки модулей с GitHub
 */
class GithubDownloader
{
    private FileSystemHelper $fileSystemHelper;

    public function __construct(FileSystemHelper $fileSystemHelper)
    {
        $this->fileSystemHelper = $fileSystemHelper;
    }

    /**
     * Парсит URL репозитория GitHub и возвращает имя модуля
     *
     * @param string $url URL репозитория
     * @return string|null
     */
    public function parseModuleName(string $url): ?string
    {
        $url = $this->normalizeUrl($url);

        if (preg_match('#github\.com/[^/]+/([^/]+)#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Загружает модуль с GitHub и распаковывает в целевую директорию
     *
     * @param string $repositoryUrl URL репозитория
     * @param string $targetPath Целевой путь
     * @return array ['success' => bool, 'message' => string]
     */
    public function download(string $repositoryUrl, string $targetPath): array
    {
        $zipUrl = $this->getZipUrl($repositoryUrl);
        $tempZip = sys_get_temp_dir() . '/' . uniqid('github_module_') . '.zip';
        $tempDir = sys_get_temp_dir() . '/' . uniqid('github_extract_');

        try {
            // Загрузка архива
            $zipContent = @file_get_contents($zipUrl);
            if ($zipContent === false) {
                throw new \RuntimeException('Не удалось загрузить архив с GitHub');
            }

            if (!file_put_contents($tempZip, $zipContent)) {
                throw new \RuntimeException('Не удалось сохранить архив во временной папке');
            }

            // Распаковка архива
            $zip = new \ZipArchive();
            if ($zip->open($tempZip) !== true) {
                throw new \RuntimeException('Не удалось открыть ZIP архив');
            }

            $this->fileSystemHelper->ensureDirectoryExists($tempDir);
            $zip->extractTo($tempDir);
            $zip->close();

            // Находим распакованную директорию
            $sourceDir = $this->fileSystemHelper->getFirstSubdirectory($tempDir);
            if (!$sourceDir) {
                throw new \RuntimeException('Не удалось найти распакованные файлы');
            }

            // Копируем файлы в целевую директорию
            if (!$this->fileSystemHelper->copyDirectory($sourceDir, $targetPath)) {
                throw new \RuntimeException('Не удалось скопировать файлы модуля');
            }

            // Очистка временных файлов
            $this->cleanup($tempZip, $tempDir);

            return [
                'success' => true,
                'message' => 'Модуль успешно загружен',
            ];
        } catch (\Exception $e) {
            // Очистка при ошибке
            $this->cleanup($tempZip, $tempDir, $targetPath);

            return [
                'success' => false,
                'message' => sprintf('Ошибка при загрузке модуля: %s', $e->getMessage()),
            ];
        }
    }

    /**
     * Нормализует URL репозитория
     *
     * @param string $url URL
     * @return string
     */
    private function normalizeUrl(string $url): string
    {
        $url = rtrim($url, '/');
        return preg_replace('/\.git$/', '', $url);
    }

    /**
     * Формирует URL для загрузки ZIP архива
     *
     * @param string $repositoryUrl URL репозитория
     * @return string
     */
    private function getZipUrl(string $repositoryUrl): string
    {
        $url = $this->normalizeUrl($repositoryUrl);
        return $url . '/archive/refs/heads/master.zip';
    }

    /**
     * Очищает временные файлы
     *
     * @param string $tempZip Путь к временному ZIP файлу
     * @param string $tempDir Путь к временной директории
     * @param string|null $targetPath Путь к целевой директории (опционально)
     */
    private function cleanup(string $tempZip, string $tempDir, ?string $targetPath = null): void
    {
        if (file_exists($tempZip)) {
            @unlink($tempZip);
        }

        if (file_exists($tempDir)) {
            $this->fileSystemHelper->removeDirectory($tempDir);
        }

        if ($targetPath && file_exists($targetPath)) {
            $this->fileSystemHelper->removeDirectory($targetPath);
        }
    }
}

