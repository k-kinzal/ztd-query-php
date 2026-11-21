<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;

/**
 * Allocates local slots for the resolved columns of a projection.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SourceBinding
{
    /**
     * @var list<Identifier> Names of the consumed columns
     */
    private array $names = [];

    /**
     * @var array<int, int> Slot by analysis declaration identity
     */
    private array $slots = [];

    private readonly ?Table $table;

    /**
     * Resolves the source from the analysis before any slots can be allocated.
     */
    public function __construct(private readonly Operation $operation, private readonly ?Relation $source)
    {
        $resolution = null;
        if ($source !== null) {
            Check::input($source instanceof TableInput && $source->index === null, 'The projection source must be an ordinary table input.');
            Check::input(
                $operation->context->relationNames === Comparison::AsciiInsensitive
                    && $operation->context->columnNames === Comparison::AsciiInsensitive,
                'The declaration context must use SQLite name comparison.',
            );
            $path = $operation->context->searchPath;
            $comparison = Comparison::AsciiInsensitive;
            Check::input(
                isset($path[1]) && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
                'The declaration context must search temp and main before attached schemas.',
            );
            $resolution = $operation->facts->relation($source)->table;
            Check::input(
                $resolution instanceof DeclaredTable && $resolution->table->complete
                    && $resolution->table->kind === RelationKind::BaseTable && $resolution->table->name->catalog === null,
                'The projection requires a resolved, complete base table declaration.',
            );
        }
        $this->table = $resolution?->table;
    }

    /**
     * Allocates or reuses a local slot for the exact resolved column.
     */
    public function reference(Scalar $expression): ColumnReference
    {
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::input(
            $resolution instanceof ResolvedColumn && $resolution->relation === $this->source && $resolution->depth === 0,
            'The column must resolve to the projection source.',
        );
        $column = $resolution->declaration();
        Check::input(
            $column !== null && $this->table !== null && in_array($column, $this->table->columns, true),
            'The column must be an explicit member of the projection source.',
        );
        $key = spl_object_id($column);
        if (!isset($this->slots[$key])) {
            $this->slots[$key] = count($this->names);
            $this->names[] = new Identifier($column->name->value);
        }

        return new ColumnReference($this->slots[$key]);
    }

    /**
     * Exports the absolute external identity and consumed column names.
     */
    public function source(): ?TableSource
    {
        return $this->table === null ? null : new TableSource(
            new Identifier(($this->table->name->schema ?? $this->operation->context->declarationSchema)->value),
            new Identifier($this->table->name->name->value),
            $this->names,
        );
    }
}
