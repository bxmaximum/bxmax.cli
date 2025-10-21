<?php

declare(strict_types=1);

namespace Bxmax\Cli\Service;

/**
 * Хелпер для работы с файловой системой
 */
class FileSystemHelper
{
    /**
     * Рекурсивно копирует директорию
     *
     * @param string $source Исходная директория
     * @param string $destination Целевая директория
     * @return bool
     */
    public function copyDirectory(string $source, string $destination): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!$this->ensureDirectoryExists($destination)) {
            return false;
        }

        $dir = opendir($source);
        if ($dir === false) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $file;
            $destPath = $destination . DIRECTORY_SEPARATOR . $file;

            if (is_dir($sourcePath)) {
                if (!$this->copyDirectory($sourcePath, $destPath)) {
                    closedir($dir);
                    return false;
                }
            } else {
                if (!copy($sourcePath, $destPath)) {
                    closedir($dir);
                    return false;
                }
                // Сохраняем права доступа
                chmod($destPath, fileperms($sourcePath));
            }
        }

        closedir($dir);
        return true;
    }

    /**
     * Рекурсивно удаляет директорию
     *
     * @param string $path Путь к директории
     * @return bool
     */
    public function removeDirectory(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        if (!is_dir($path)) {
            return unlink($path);
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        return rmdir($path);
    }

    /**
     * Проверяет существование директории и создает её при необходимости
     *
     * @param string $path Путь к директории
     * @param int $permissions Права доступа
     * @return bool
     */
    public function ensureDirectoryExists(string $path, int $permissions = 0755): bool
    {
        if (file_exists($path)) {
            return is_dir($path);
        }

        return mkdir($path, $permissions, true);
    }

    /**
     * Получает первую поддиректорию в указанной директории
     *
     * @param string $path Путь к директории
     * @return string|null
     */
    public function getFirstSubdirectory(string $path): ?string
    {
        if (!is_dir($path)) {
            return null;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($path . DIRECTORY_SEPARATOR . $item)) {
                return $path . DIRECTORY_SEPARATOR . $item;
            }
        }

        return null;
    }
}

