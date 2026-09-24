<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Schema;
use SqlSemantics\Schema\TableDefinition;
use SqlSemantics\SemanticException;

/**
 * Resolves a qualified table name against an explicit schema snapshot.
 *
 * @visibility SqlSemantics
 */
final class TableResolver
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Schema $schema, public readonly Identifiers $identifiers, public readonly string $defaultSchema, public readonly Analysis\Diagnostics $diagnostics = new Analysis\Diagnostics(), public readonly ?Statement\Routine\Program\ProgramNamespace $program = null)
    {
    }

    /**
     * @param list<string> $parts
     * @throws SemanticException
     */
    public function resolve(array $parts, Node $source): TableDefinition
    {
        $schema = count($parts) === 2 ? $parts[0] : $this->searched($parts[count($parts) - 1]);
        $name = $parts[count($parts) - 1];
        $matches = [];
        foreach ($this->schema->tables as $table) {
            $matchesName = $this->identifiers->relationEqual($table->schema, $schema) && $this->identifiers->relationEqual($table->name, $name);
            if ($matchesName && count($parts) <= 2) {
                $matches[] = $table;
            }
        }
        if (count($matches) !== 1) {
            $this->diagnostics->report($matches === [] ? 'unknown-table' : 'ambiguous-table', 'Cannot resolve table: ' . implode('.', $parts), $source);
            return new TableDefinition($schema, $name, [], [], $source, resolved: false);
        }

        return $matches[0];
    }
    /**
     * Returns the schema an unqualified table name resolves in: a temporary table in PostgreSQL's pg_temp or SQLite's temp schema hides a table of the same name in the default schema.
     */
    public function searched(string $name): string
    {
        $temporary = match ($this->identifiers->dialect) {
            \SqlSemantics\Dialect::PostgreSql => 'pg_temp',
            \SqlSemantics\Dialect::Sqlite => 'temp',
            \SqlSemantics\Dialect::MySql => null,
        };
        if ($temporary === null) {
            return $this->defaultSchema;
        }
        foreach ($this->schema->tables as $table) {
            if ($this->identifiers->relationEqual($table->schema, $temporary) && $this->identifiers->relationEqual($table->name, $name)) {
                return $temporary;
            }
        }
        return $this->defaultSchema;
    }

    /**
     * Retains explicit namespaces, including a quoted empty namespace.
     *
     * @param list<string> $parts Source identifier parts
     */
    public function name(array $parts, TableDefinition $table): \SqlSemantics\Model\Relation\QualifiedName
    {
        return new \SqlSemantics\Model\Relation\QualifiedName(count($parts) === 2 || $table->schema !== '' ? [$table->schema, $table->name] : [$table->name]);
    }

}
