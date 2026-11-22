<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\From\SourceDeclarations;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;

/**
 * Allocates column slots independently for each local occurrence.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SourceBinding
{
    /**
     * @var list<array{TableInput|MutationTarget, Table}>
     */
    private readonly array $tables;

    /**
     * @var array<int, array<int, int>>
     */
    private array $slots = [];

    /**
     * @var array<int, list<Identifier>>
     */
    private array $names = [];

    /**
     * Resolves the relation occurrences before allocating column slots.
     * @param non-empty-list<Identifier>|null $localColumns The slots of a verified local input
     */
    public function __construct(
        private readonly BoundStatement $operation,
        private readonly ?Relation $source,
        private readonly ?array $localColumns = null,
    ) {
        $this->tables = $localColumns === null ? (new SourceDeclarations())->bind($operation, $source) : [];
    }

    /**
     * Allocates or reuses a slot for the exact occurrence and resolved column pair.
     *
     * @throws InvalidConstruction When the expression has no local column binding
     */
    public function reference(Scalar $expression): ColumnReference
    {
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::input($resolution instanceof ResolvedColumn, 'The column must resolve to the projection source.');

        return $this->resolved($resolution);
    }

    /**
     * Allocates the occurrence slot of an already resolved projection field.
     *
     * @throws InvalidConstruction When the field is outside the checked input scope
     */
    public function resolved(ResolvedColumn $resolution): ColumnReference
    {
        Check::input(
            $resolution->depth === 0,
            'The column must resolve to the projection source.'
        );
        if ($this->localColumns !== null) {
            $name = $resolution->slot->name;
            Check::input($resolution->relation === $this->source && $name !== null, 'The column must resolve to the local input occurrence.');
            $key = (new Identifier($name->value))->key();
            foreach ($this->localColumns as $index => $column) {
                if ($column->key() === $key) {
                    return new ColumnReference($index);
                }
            }
            throw new InvalidConstruction('The column must belong to the local input.');
        }
        $column = $resolution->declaration();
        foreach ($this->tables as $index => [$occurrence, $table]) {
            if ($resolution->relation === $occurrence) {
                Check::input($column !== null, 'The column must be an explicit member of the projection source.');
                return new ColumnReference($this->slot($index, $column), $index);
            }
        }

        throw new InvalidConstruction('The column must resolve to a local base-table occurrence.');
    }

    /**
     * Resolves a writable declared name in the single mutation target.
     */
    public function write(Name $name): int
    {
        [$occurrence, $table] = $this->tables[0] ?? [null, null];
        Check::invariant($occurrence instanceof MutationTarget && $table !== null, 'A written slot belongs to a bound mutation target.');
        $matches = $table->matchingColumns($name->value, Comparison::AsciiInsensitive);
        Check::input(
            count($matches) === 1 && in_array($matches[0], $table->columns, true) && !$matches[0]->generated,
            'An assigned name must resolve to a writable declared column.',
        );

        return $this->slot(0, $matches[0]);
    }

    /**
     * Records a resolved column once within its occurrence.
     */
    public function slot(int $occurrence, Column $column): int
    {
        $table = $this->tables[$occurrence][1] ?? null;
        Check::input($table !== null && in_array($column, $table->columns, true), 'The column must be an explicit member of the projection source.');
        $key = spl_object_id($column);
        if (!isset($this->slots[$occurrence][$key])) {
            $this->slots[$occurrence][$key] = count($this->names[$occurrence] ?? []);
            $this->names[$occurrence][] = new Identifier($column->name->value);
        }

        return $this->slots[$occurrence][$key];
    }

    /**
     * Exports the external identity and consumed column names.
     */
    public function source(int $index = 0): ?TableSource
    {
        $table = $this->tables[$index][1] ?? null;

        return $table === null ? null : new TableSource(
            new Identifier(($table->name->schema ?? $this->operation->context->declarationSchema)->value),
            new Identifier($table->name->name->value),
            $this->names[$index] ?? [],
        );
    }
}
