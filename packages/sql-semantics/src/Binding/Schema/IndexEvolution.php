<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Definition\IndexReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * Applies CREATE INDEX and DROP INDEX to an immutable schema snapshot.
 *
 * @visibility SqlSemantics
 */
final class IndexEvolution
{
    /**
     * Uses the current table definitions to resolve index destinations.
     */
    public function __construct(public readonly TableResolver $tables)
    {
    }

    /**

     * @return list<TableDefinition>

     */
    public function apply(Node $statement): array
    {
        $index = (new IndexReader($this->tables->identifiers, $this->tables->defaultSchema))->read($statement);
        if ($index !== null) {
            $target = $this->tables->resolve($index->table, $statement);
            $names = array_column($target->columns, 'name');
            foreach ([...array_filter(array_column($index->elements, 'column'), static fn (?string $column): bool => $column !== null), ...$index->include] as $name) {
                if (array_filter($names, fn (string $column): bool => $this->tables->identifiers->equal($column, $name)) === []) {
                    $this->tables->diagnostics->report('unknown-column', 'Index references unknown column: ' . $name, $statement);
                }
            }
            return array_map(fn (TableDefinition $table): TableDefinition => $table === $target ? $this->add($table, $index) : $table, $this->tables->schema->tables);
        }
        if (!str_starts_with(strtoupper(Tree::text($statement)), 'DROP INDEX ')) {
            return $this->tables->schema->tables;
        }
        return $this->drop($statement);

    }

    /**

     * Adds one index while respecting IF NOT EXISTS.

     */
    public function add(TableDefinition $table, IndexDefinition $index): TableDefinition
    {
        foreach ($table->indexes as $existing) {
            if ($index->name !== null && $existing->name === $index->name) {
                if (($index->options['if_not_exists'] ?? false) === true) {
                    return $table;
                }
                $this->tables->diagnostics->report('duplicate-index', 'Duplicate index declaration: ' . $index->name, $index->source);
            }
        }
        return new TableDefinition($table->schema, $table->name, $table->columns, $table->constraints, $table->source, $table->resolved, [...$table->indexes, $index], $table->options);
    }
    /**
     * Removes only indexes in the named namespace and, for MySQL, the named table.
     *
     * @return list<TableDefinition>
     */
    public function drop(Node $statement): array
    {
        $names = Tree::outer($statement, ['any_name', 'fullname', 'ident', 'table_ident']);
        $tableNode = Tree::outer($statement, ['table_ident'])[0] ?? null;
        $target = $tableNode === null ? null : $this->tables->resolve($this->tables->identifiers->parts($tableNode), $tableNode);
        $drop = [];
        foreach ($names as $name) {
            if ($name->name !== 'table_ident') {
                $parts = $this->tables->identifiers->parts($name);
                $drop[] = count($parts) === 1 ? [$target->schema ?? $this->tables->defaultSchema, $parts[0]] : $parts;
            }
        }
        $result = [];
        foreach ($this->tables->schema->tables as $table) {
            if ($target !== null && $table !== $target) {
                $result[] = $table;
                continue;
            }
            $indexes = array_values(array_filter($table->indexes, fn (IndexDefinition $index): bool => array_filter($drop, fn (array $parts): bool => $this->tables->identifiers->relationEqual($index->schema, $parts[0]) && $index->name !== null && $this->tables->identifiers->equal($index->name, $parts[1])) === []));
            $result[] = new TableDefinition($table->schema, $table->name, $table->columns, $table->constraints, $table->source, $table->resolved, $indexes, $table->options);
        }
        return $result;
    }

}
