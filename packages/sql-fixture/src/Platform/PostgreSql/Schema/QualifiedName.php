<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlParser\Lexer\SourceException;
use SqlParser\PostgreSql\PostgreSqlParser;

/**
 * Reads a table name into the schema and the table the catalog stores.
 *
 * The name is read with the grammar of the server, so a qualifier is told
 * apart from a dot written inside a quoted name, and an unquoted name is
 * folded to lower case the way the server folds it before the catalog sees
 * it. A name the grammar does not read as one table is left as it was given.
 *
 * @visibility root
 */
final class QualifiedName
{
    /**
     * Keeps the grammar the name is read with.
     */
    public function __construct(private readonly PostgreSqlParser $parser = new PostgreSqlParser())
    {
    }

    /**
     * Answers the schema and the table the name refers to, the schema defaulting to public.
     *
     * @return array{string, string} The schema and the table as the catalog spells them
     */
    public function split(string $tableName): array
    {
        $parts = $this->parts($tableName) ?? [$tableName];
        $count = count($parts);

        return [$count >= 2 ? $parts[$count - 2] : 'public', $parts[$count - 1]];
    }

    /**
     * Answers the identifiers the name is written from, or null when it does not read as one table.
     *
     * @return non-empty-list<string>|null The identifiers, outermost first
     */
    public function parts(string $tableName): ?array
    {
        try {
            $tree = $this->parser->parse('TABLE ' . $tableName);
        } catch (SourceException) {
            return null;
        }
        $names = $tree->find('qualified_name');
        if (count($names) !== 1 || count($tree->find('toplevel_stmt')) !== 1) {
            return null;
        }

        $parts = [];
        foreach ($names[0]->tokens() as $token) {
            if ($token->text !== '.') {
                $parts[] = (new Identifier())->fold($token);
            }
        }

        return $parts === [] ? null : $parts;
    }
}
