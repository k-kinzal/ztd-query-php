<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Exception\InvalidInputException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Explicit source bytes captured without executing autoloaders or bootstrap files.
 *
 * @visibility public
 * @example Supplying an in-memory project
 *     $input = new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php return 1;')]);
 *     count($input->files) // => 1
 */
final class ProjectInput
{
    /**
     * @param list<SourceFile> $files Captured source files
     * @throws InvalidInputException If paths are empty or duplicated after normalization
     */
    public function __construct(public readonly array $files)
    {
        $seen = [];
        foreach ($files as $file) {
            $path = self::normalize($file->path);
            if ($path === '' || isset($seen[$path])) {
                throw new InvalidInputException('Source paths must be nonempty and unique: ' . $path);
            }
            $seen[$path] = true;
        }
    }

    /**
     * Captures explicitly selected local files.
     * @param list<string> $paths Local PHP source paths
     * @return self Immutable source contents
     * @throws InvalidInputException If a path is unreadable or uses a stream wrapper
     */
    public static function fromFiles(array $paths): self
    {
        $files = [];
        foreach ($paths as $path) {
            if (str_contains($path, '://') || !is_file($path) || !is_readable($path)) {
                throw new InvalidInputException('Source must be a readable local file: ' . $path);
            }
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new InvalidInputException('Cannot capture source: ' . $path);
            }
            $files[] = new SourceFile(self::normalize($path), $contents);
        }
        return new self($files);
    }

    /**
     * Captures PHP files below one directory without following symlink directories.
     * @param string $directory Local source root
     * @param list<string> $exclude Directory components to exclude
     * @return self Captured PHP source
     * @throws InvalidInputException If the source root is not a readable local directory
     */
    public static function fromDirectory(string $directory, array $exclude = ['vendor', '.git', 'build']): self
    {
        if (str_contains($directory, '://') || !is_dir($directory) || !is_readable($directory)) {
            throw new InvalidInputException('Source root must be a readable local directory.');
        }
        $root = rtrim(self::normalize($directory), '/');
        $files = [];
        $directories = new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), static fn (SplFileInfo $file): bool => !$file->isLink() && !in_array($file->getFilename(), $exclude, true));
        $iterator = new RecursiveIteratorIterator($directories);
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->isLink() || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $path = self::normalize($file->getPathname());
            $relative = $root === '' ? ltrim($path, '/') : substr($path, strlen($root) + 1);
            $captured = self::fromFiles([$file->getPathname()]);
            $files[] = new SourceFile($relative, $captured->files[0]->contents);
        }
        usort($files, static fn (SourceFile $a, SourceFile $b): int => strcmp($a->path, $b->path));
        return new self($files);
    }

    /**
     * Normalizes path separators and dot components without filesystem access.
     * @param string $path Source path
     * @return string Normalized identity
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..' && $parts !== [] && end($parts) !== '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }
        return (str_starts_with($path, '/') ? '/' : '') . implode('/', $parts);
    }
}
