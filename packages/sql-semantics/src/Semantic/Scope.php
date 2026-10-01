<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic;

use SqlSemantics\Core\Dialect;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Reference\AmbiguousColumn;
use SqlSemantics\Semantic\Reference\CandidateColumn;
use SqlSemantics\Semantic\Reference\MissingColumn;
use SqlSemantics\Semantic\Reference\ResolvedColumn;
use SqlSemantics\Semantic\Relation\Join;
use SqlSemantics\Semantic\Relation\Relations;
use SqlSemantics\Semantic\Relation\TableReference;

/**
 * A closed set of visible relation occurrences, with optional declarations.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT foo FROM bar');
 *     count($statement->scope->tables) // => 1
 *
 * @visibility public
 */
final class Scope
{
    /**
     * @var list<TableReference>
     */
    public readonly array $tables;
    /**
     * @var list<TableReference|Join>
     */
    public readonly array $sources;
    /**
     * @var list<TableReference>
     */
    public readonly array $nullableRelations;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Dialect $dialect, TableReference|Join ...$sources)
    {
        $this->sources = array_values($sources);
        $tables = [];
        $nullable = [];
        foreach ($sources as $source) {
            array_push($tables, ...Relations::tables($source));
            array_push($nullable, ...Relations::nullable($source));
        }
        $this->nullableRelations = $nullable;
        $names = [];
        foreach ($tables as $table) {
            assert($table->dialect === $dialect, 'A relation must use its scope dialect.');
            assert($table->catalogSupplied === $tables[0]->catalogSupplied, 'A scope uses one catalog policy.');
            foreach ($names as $name) {
                assert(!$dialect->platform()->names()->relationEqual($name, $table->visibleName()->value), 'Duplicate relation name in scope.');
            }
            $names[] = $table->visibleName()->value;
        }
        $this->tables = $tables;
    }

    /**
     * Creates a column reference whose facts are derived from this scope.
     */
    public function column(Name $name, ?QualifiedName $qualifier = null): ColumnReference
    {
        return new ColumnReference($this, $name, $qualifier);
    }

    /**
     * Distinguishes declaration identity, candidates, missing columns, and ambiguity.
     */
    public function resolve(Name $name, ?QualifiedName $qualifier): ResolvedColumn|CandidateColumn|MissingColumn|AmbiguousColumn
    {
        $matches = [];
        $candidates = [];
        foreach ($this->tables as $table) {
            if (!$this->matches($table, $qualifier)) {
                continue;
            }
            if (!$table->catalogSupplied) {
                $candidates[] = $table;
                continue;
            }
            $column = $table->declaration?->column($name->value);
            if ($column !== null) {
                $matches[] = new ResolvedColumn($table, $column);
            }
        }
        if (count($matches) > 1) {
            return new AmbiguousColumn($matches[0], $matches[1], ...array_slice($matches, 2));
        }
        if ($candidates !== []) {
            assert($matches === [], 'A scope cannot mix open and closed catalogs.');
            return new CandidateColumn($candidates[0], ...array_slice($candidates, 1));
        }
        return $matches[0] ?? new MissingColumn();
    }

    /**
     * Checks a qualifier against the visible name of a relation occurrence.
     */
    public function matches(TableReference $table, ?QualifiedName $qualifier): bool
    {
        if ($qualifier === null) {
            return true;
        }
        $rules = $this->dialect->platform()->names();
        if (!$rules->relationEqual($table->visibleName()->value, $qualifier->name->value)) {
            return false;
        }
        if ($qualifier->schema === null) {
            return true;
        }
        $schema = $table->name->schema->value ?? $this->dialect->platform()->defaultSchema();
        return $table->alias === null && $rules->relationEqual($schema, $qualifier->schema->value);
    }
}
