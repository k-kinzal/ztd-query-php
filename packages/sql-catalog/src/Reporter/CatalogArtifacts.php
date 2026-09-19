<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter;

/**
 * The files a reporter produced, held in memory until something writes them.
 *
 * Keeping the rendering separate from the writing is what lets the same
 * reporter print to standard output and write into a directory.
 *
 * @visibility root
 */
final class CatalogArtifacts
{
    /**
     * @var array<string, string>
     */
    private array $files;

    /**
     * @param array<string, string> $files Contents, keyed by the name to write them under
     */
    public function __construct(array $files = [])
    {
        $this->files = $files;
    }

    /**
     * The artifacts holding one file.
     */
    public static function one(string $name, string $contents): self
    {
        return new self([$name => $contents]);
    }

    /**
     * The contents, keyed by file name, in name order.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $files = $this->files;
        ksort($files);

        return $files;
    }

    /**
     * The file names, in order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    /**
     * The contents of the only file, or null when there is not exactly one.
     */
    public function sole(): ?string
    {
        $files = array_values($this->files);

        return count($files) === 1 ? $files[0] : null;
    }

    /**
     * The contents written under a name, or null when nothing is.
     */
    public function get(string $name): ?string
    {
        return $this->files[$name] ?? null;
    }
}
