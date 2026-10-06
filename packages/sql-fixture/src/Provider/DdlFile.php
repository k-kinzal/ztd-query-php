<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use RuntimeException;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Reads a DDL file and rejects statements outside the schema grammar.
 *
 * @visibility root
 */
final class DdlFile
{
    /**
     * Load a single SQL file.
     * @throws RuntimeException
     */
    public function loadSchemaFile(string $filePath, SchemaParserInterface $parser): ?TableSchema
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        try {
            return $parser->parse($content);
        } catch (SchemaParseException) {
            return null;
        }
    }
}
