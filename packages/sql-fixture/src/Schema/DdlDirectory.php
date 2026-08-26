<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use RuntimeException;

/**
 * Reads every table declared by the `.sql` files in a directory.
 *
 * A directory of DDL is how a project keeps its schema under version control,
 * and it usually holds more than table declarations: grants, seed data,
 * migrations that alter rather than create. A file that yields no table is
 * therefore passed over rather than reported, so a fixture can be generated
 * from a directory nobody curated for it.
 */
final class DdlDirectory
{
    /**
     * @param SchemaParserInterface $parser Reads a declaration as a table
     */
    public function __construct(private readonly SchemaParserInterface $parser)
    {
    }

    /**
     * Reads every table the directory declares.
     *
     * @param string $path Directory to read
     *
     * @return array<string, TableSchema> Lowercased table name => the table
     *
     * @throws RuntimeException When the path is not a directory, or cannot be listed
     */
    public function tables(string $path): array
    {
        if (!is_dir($path)) {
            throw new RuntimeException("DDL path is not a directory: {$path}");
        }

        $files = glob($path . '/*.sql');
        if ($files === false) {
            throw new RuntimeException("Failed to read DDL directory: {$path}");
        }

        $tables = [];
        foreach ($files as $file) {
            $table = $this->tableIn($file);
            if ($table !== null) {
                $tables[strtolower($table->tableName)] = $table;
            }
        }

        return $tables;
    }

    /**
     * Reads the table one file declares, where it declares one.
     *
     * @param string $path File to read
     *
     * @return TableSchema|null The table, or null when the file declares none
     *
     * @throws RuntimeException When the file cannot be read
     */
    public function tableIn(string $path): ?TableSchema
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Failed to read file: {$path}");
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$path}");
        }

        $withoutComments = preg_replace('/\/\*.*?\*\//s', '', preg_replace('/--.*$/m', '', $contents) ?? '');
        if ($withoutComments === null || trim($withoutComments) === '') {
            return null;
        }

        try {
            return $this->parser->parse($withoutComments);
        } catch (SchemaParseException) {
            return null;
        }
    }
}
