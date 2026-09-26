<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Reporter;

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

    private ?string $primary;

    /**
     * @param array<string, string> $files Contents, keyed by the name to write them under
     * @param string|null $primary The file a reader wants when only one can be shown
     */
    public function __construct(array $files = [], ?string $primary = null)
    {
        $this->files = $files;
        $this->primary = $primary;
    }

    /**
     * The artifacts holding one file.
     */
    public static function one(string $name, string $contents): self
    {
        return new self([$name => $contents], $name);
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
     * The contents a reader wants when only one file can be shown.
     *
     * Writing into a directory can produce more than one file — a document and
     * the schema that describes it. Printing cannot, so one of them is the one
     * that gets printed.
     */
    public function primary(): ?string
    {
        if ($this->primary !== null) {
            return $this->files[$this->primary] ?? null;
        }

        return $this->sole();
    }

    /**
     * The contents written under a name, or null when nothing is.
     */
    public function get(string $name): ?string
    {
        return $this->files[$name] ?? null;
    }
}
