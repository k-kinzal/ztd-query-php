<?php

declare(strict_types=1);

namespace SqlFixture\CodeGen;

use RuntimeException;
use SqlFixture\Schema\TableSchema;

/**
 * Writes one class per table into a directory.
 *
 * The output is meant to be committed. A generated file that only exists on
 * the machine that ran the generator cannot be reviewed, and a schema change
 * that should have broken a test would instead break the build somewhere far
 * from the cause.
 */
final class SchemaCodeGenerator
{
    private TableClassGenerator $classes;

    public function __construct(?TableClassGenerator $classes = null)
    {
        $this->classes = $classes ?? new TableClassGenerator();
    }

    /**
     * @param iterable<TableSchema> $schemas
     * @return array<string, string> File name => contents
     */
    public function generate(iterable $schemas, string $namespace): array
    {
        $files = [];

        foreach ($schemas as $schema) {
            $files[$this->classes->className($schema) . '.php'] = $this->classes->generate($schema, $namespace);
        }

        ksort($files);

        return $files;
    }

    /**
     * @param iterable<TableSchema> $schemas
     * @return list<string> The paths written
     */
    public function write(iterable $schemas, string $namespace, string $directory): array
    {
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create the output directory: %s', $directory));
        }

        $written = [];

        foreach ($this->generate($schemas, $namespace) as $name => $contents) {
            $path = rtrim($directory, '/') . '/' . $name;

            if (file_put_contents($path, $contents) === false) {
                throw new RuntimeException(sprintf('Could not write: %s', $path));
            }

            $written[] = $path;
        }

        return $written;
    }
}
