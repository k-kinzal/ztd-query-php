<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\From\SourceDeclarations;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
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
     */
    public function __construct(private readonly Operation $operation, ?Relation $source)
    {
        $this->tables = (new SourceDeclarations())->bind($operation, $source);
    }

    /**
     * Allocates or reuses a slot for the exact occurrence and resolved column pair.
     *
     * @throws InvalidConstruction When the expression has no local column binding
     */
    public function reference(Scalar $expression): ColumnReference
    {
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::input($resolution instanceof ResolvedColumn && $resolution->depth === 0, 'The column must resolve to the projection source.');
        $column = $resolution->declaration();
        foreach ($this->tables as $index => [$occurrence, $table]) {
            if ($resolution->relation === $occurrence) {
                Check::input($column !== null && in_array($column, $table->columns, true), 'The column must be an explicit member of the projection source.');
                $key = spl_object_id($column);
                if (!isset($this->slots[$index][$key])) {
                    $this->slots[$index][$key] = count($this->names[$index] ?? []);
                    $this->names[$index][] = new Identifier($column->name->value);
                }

                return new ColumnReference($this->slots[$index][$key], $index);
            }
        }

        throw new InvalidConstruction('The column must resolve to a local base-table occurrence.');
    }

    /**
     * Exports the external identity and consumed column names of the specified occurrence.
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
