<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Exception\InvalidInputException;
use Deriver\Finder\DirectoryScan;
use Deriver\Finder\PathPattern;

/**
 * Finds local file paths without reading or parsing their contents.
 * Patterns match paths relative to each root: * and ? stay within a component.
 * The recursive ** wildcard crosses directories and can match zero directories. Exclusions win.
 * Linked files and directories are skipped. With no roots, searches the current directory;
 * with no includes, selects every regular file. No directories are excluded implicitly.
 * @visibility public
 * @example Selecting only PHP source paths
 *     $finder = (new \Deriver\Finder())->in(__DIR__)->include('*.php')->exclude('vendor');
 *     is_array($finder->files()) // => true
 */
final class Finder
{
    /**
     * @var list<string>
     */
    private array $directories = [];
    /**
     * @var list<PathPattern>
     */
    private array $includes = [];
    /**
     * @var list<PathPattern>
     */
    private array $excludes = [];

    /**
     * Adds local search roots. Results are sorted, unique absolute paths.
     * @throws InvalidInputException If a root is not a readable local directory
     */
    public function in(string $directory, string ...$directories): self
    {
        foreach ([$directory, ...$directories] as $root) {
            if (str_contains($root, '://') || !is_dir($root) || !is_readable($root)) {
                throw new InvalidInputException('Finder root must be a readable local directory: ' . $root);
            }
            $absolute = realpath($root);
            if ($absolute === false) {
                throw new InvalidInputException('Cannot resolve Finder root: ' . $root);
            }
            $this->directories[] = str_replace('\\', '/', $absolute);
        }
        return $this;
    }

    /**
     * Includes files matching any supplied root-relative wildcard pattern.
     */
    public function include(string $pattern, string ...$patterns): self
    {
        foreach ([$pattern, ...$patterns] as $value) {
            $this->includes[] = new PathPattern($value);
        }
        return $this;
    }

    /**
     * Excludes matching files and directory subtrees, even when included explicitly.
     */
    public function exclude(string $pattern, string ...$patterns): self
    {
        foreach ([$pattern, ...$patterns] as $value) {
            $this->excludes[] = new PathPattern($value);
        }
        return $this;
    }

    /**
     * Enumerates paths only; file contents are read later by Deriver::prepare().
     * @return list<string> Sorted, unique absolute paths
     */
    public function files(): array
    {
        $directories = $this->directories === [] ? (new self())->in('.')->directories : $this->directories;
        $paths = [];
        foreach (array_unique($directories) as $directory) {
            foreach ((new DirectoryScan())->files($directory, $this->includes, $this->excludes) as $path) {
                $paths[$path] = true;
            }
        }
        $files = array_keys($paths);
        sort($files, SORT_STRING);
        return $files;
    }
}
