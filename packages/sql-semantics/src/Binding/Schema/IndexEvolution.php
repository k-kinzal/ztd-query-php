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
            $scope = new \SqlSemantics\Binding\Scope($this->tables->identifiers, [new \SqlSemantics\Model\Relation\TableReference('declaration', 'declaration', $target, new \SqlSemantics\Model\Relation\QualifiedName($target->schema === '' ? [$target->name] : [$target->schema, $target->name]), null, $statement)], queries: new \SqlSemantics\Binding\Query\QueryContext($this->tables));
            $definition = IndexBinder::definition($index, $scope);
            return array_map(fn (TableDefinition $table): TableDefinition => $table === $target ? $this->add($table, $definition, ($index->options['if_not_exists'] ?? false) === true) : $table, $this->tables->schema->tables);
        }
        if (!str_starts_with(strtoupper(Tree::text($statement)), 'DROP INDEX ')) {
            return $this->tables->schema->tables;
        }
        return $this->drop($statement);

    }

    /**
     * Adds one index while respecting IF NOT EXISTS; an unnamed PostgreSQL index takes the name the server gives it.
     */
    public function add(TableDefinition $table, IndexDefinition $index, bool $ifNotExists = false): TableDefinition
    {
        if ($this->tables->identifiers->dialect === \SqlSemantics\Dialect::PostgreSql) {
            $index = Constraint\PostgreSqlIndexNames::assign($index, $table, $this->tables->schema->tables);
        }
        foreach ($table->indexes as $existing) {
            if ($index->name !== null && $existing->name === $index->name) {
                if ($ifNotExists) {
                    return $table;
                }
                $this->tables->diagnostics->report('duplicate-index', 'Duplicate index declaration: ' . $index->name, $index->source);
            }
        }
        return new TableDefinition($table->schema, $table->name, $table->columns, $table->constraints, $table->source, $table->resolved, [...$table->indexes, $index], $table->properties);
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
        if ($target !== null && $this->tables->identifiers->dialect === \SqlSemantics\Dialect::MySql) {
            return $this->dropKey($target, $names);
        }
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
            $result[] = new TableDefinition($table->schema, $table->name, $table->columns, $table->constraints, $table->source, $table->resolved, $indexes, $table->properties);
        }
        return $result;
    }

    /**
     * Applies a MySQL DROP INDEX as ALTER TABLE DROP INDEX does: the name reaches an index, a unique key, or, as
     * PRIMARY, the primary key of the named table; dropping the key an AUTO_INCREMENT column needs is rejected.
     *
     * @throws \SqlSemantics\InvalidSql
     * @param list<Node> $names
     * @return list<TableDefinition>
     */
    public function dropKey(TableDefinition $target, array $names): array
    {
        $identifiers = $this->tables->identifiers;
        $keys = new Alter\KeyChanges($target, $target->constraints, $target->indexes);
        foreach ($names as $name) {
            if ($name->name !== 'table_ident') {
                $parts = $identifiers->parts($name);
                $keys = $keys->drop(\SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind::Index, $parts[count($parts) - 1], new \SqlSemantics\Binding\Scope($identifiers));
            }
        }
        $replacement = new TableDefinition($target->schema, $target->name, $target->columns, $keys->constraints, $target->source, $target->resolved, $keys->indexes, $target->properties);
        Constraint\MySqlCounterKeys::check($replacement, $names[0] ?? $target->source);
        return array_map(static fn (TableDefinition $table): TableDefinition => $table === $target ? $replacement : $table, $this->tables->schema->tables);
    }
}
