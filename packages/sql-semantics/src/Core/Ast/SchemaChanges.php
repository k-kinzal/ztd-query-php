<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Schema\TableDefinition;
use SqlSemantics\Core\SemanticException;

/**
 * Applies DROP TABLE to the local closed catalog in declaration order.
 * @visibility SqlSemantics
 */
final class SchemaChanges
{
    /**
     * Selects name resolution policy for the local snapshot.
     */
    public function __construct(private readonly Identifiers $identifiers, private readonly string $defaultSchema)
    {
    }

    /**
     * Returns false for other statement kinds; missing unconditional drops fail.
     * @param array<string, TableDefinition> $tables
     * @throws SemanticException When a required relation is absent
     */
    public function drop(Node $statement, array &$tables): bool
    {
        if (preg_match('/^DROP (?:TEMPORARY )?TABLES? /i', Tree::text($statement)) !== 1) {
            return false;
        }
        $optional = preg_match('/^DROP (?:TEMPORARY )?TABLES? IF EXISTS /i', Tree::text($statement)) === 1;
        foreach (Tree::outer($statement, $this->identifiers->dialect->platform()->syntax()->nodes('dropTableName')) as $name) {
            $parts = $this->identifiers->parts($name);
            if (count($parts) > 2) {
                Tree::unsupported($name, 'three-part table name');
            }
            $schema = count($parts) === 2 ? $parts[0] : $this->defaultSchema;
            $tableName = $parts[count($parts) - 1];
            $found = false;
            foreach ($tables as $key => $table) {
                if ($this->identifiers->dialect->platform()->names()->relationEqual($table->schema, $schema) && $this->identifiers->dialect->platform()->names()->relationEqual($table->name, $tableName)) {
                    unset($tables[$key]);
                    $found = true;
                }
            }
            if (!$found && !$optional) {
                throw new SemanticException('unknown-table', 'Cannot drop an unknown table: ' . $tableName, $name);
            }
        }
        return true;
    }
}
