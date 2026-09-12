<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use RuntimeException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Loads table schemas from the SQL files in a directory.
 *
 * @visibility root
 */
final class DdlDirectory
{
    /**
     * Load all SQL files from the DDL directory.
     *
     * @return array<string, TableSchema>
     * @throws RuntimeException
     */
    public function loadSchemas(string $ddlPath, SchemaParserInterface $parser): array
    {
        if (!is_dir($ddlPath)) {
            throw new RuntimeException("DDL path is not a directory: {$ddlPath}");
        }

        $files = glob($ddlPath . '/*.sql');
        if ($files === false) {
            throw new RuntimeException("Failed to read DDL directory: {$ddlPath}");
        }

        $schemas = [];
        foreach ($files as $file) {
            $schema = (new DdlFile())->loadSchemaFile($file, $parser);
            if ($schema !== null) {
                $schemas[strtolower($schema->tableName)] = $schema;
            }
        }

        return $schemas;
    }
}
