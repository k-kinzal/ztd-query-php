<?php

declare(strict_types=1);

namespace Tests\Fake;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * A requirements project in a temporary directory, removed when the object is destroyed.
 *
 * It starts with source.html, a requirements.yaml naming definition.yaml without a coverage
 * gate, and a definition.yaml quoting one paragraph of the source.
 */
final class ProjectDirectory
{
    /**
     * The absolute path of the project directory.
     */
    public readonly string $directory;

    /**
     * Creates the project with its default files.
     *
     * @throws RuntimeException When the directory cannot be created
     */
    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/requirements-test-' . bin2hex(random_bytes(8));
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Cannot create test workspace.');
        }
        $this->put('source.html', SourceDocuments::HTML);
        $this->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'coverage' => ['minimum' => 0]]);
        $this->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [self::item()]]);
    }

    /**
     * Returns the item of the default definition.
     *
     * @return array<string, mixed> A specification quoting the first paragraph of source.html
     */
    public static function item(): array
    {
        return ['id' => 'SPEC-001', 'statement' => 'When a name is read, the parser shall require a leading letter.', 'evidence' => [['selector' => '#a', 'quote' => 'Names shall start with a letter.']]];
    }

    /**
     * Writes data as a YAML document.
     *
     * @param string $file The path relative to the project
     * @param array<string, mixed> $data The document
     */
    public function write(string $file, array $data): void
    {
        $this->put($file, Yaml::dump($data, 12, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
    }

    /**
     * Writes a file, creating its directory.
     *
     * @param string $file The path relative to the project
     * @param string $contents The contents
     *
     * @return string The absolute path
     */
    public function put(string $file, string $contents): string
    {
        $path = $this->path($file);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $contents);
        return $path;
    }

    /**
     * Reads a file of the project.
     *
     * @param string $file The path relative to the project
     *
     * @return string The contents, or an empty string when the file cannot be read
     */
    public function read(string $file): string
    {
        $contents = is_file($this->path($file)) ? file_get_contents($this->path($file)) : false;
        return $contents === false ? '' : $contents;
    }

    /**
     * Returns the absolute path of a project file.
     *
     * @param string $file The path relative to the project
     *
     * @return string The absolute path
     */
    public function path(string $file): string
    {
        return $this->directory . '/' . $file;
    }

    /**
     * Removes the directory and everything in it.
     */
    public function __destruct()
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }
}
