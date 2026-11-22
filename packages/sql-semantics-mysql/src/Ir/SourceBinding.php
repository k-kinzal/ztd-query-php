<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * Allocates local slots for a resolved table occurrence.
 *
 * @visibility SqlSemantics\Platform\MySql
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
    public function __construct(private readonly BoundStatement $operation, private readonly ?Relation $source)
    {
        $resolution = null;
        if ($source !== null) {
            Check::input((($source instanceof TableReference && $source->indexHints === [] && $source->sample === null) || $source instanceof WriteTarget) && $source->partitions === [], 'The projection source must be an ordinary table input.');
            Check::input(
                $operation->context->relationNames === Comparison::Sensitive
                    && $operation->context->columnNames === Comparison::AsciiInsensitive,
                'The declaration context must use MySQL name comparison.',
            );
            $resolution = $operation->facts->relation($source)->table;
            Check::input(
                $resolution instanceof DeclaredTable && $resolution->table->complete
                    && $resolution->table->kind === RelationKind::BaseTable && $resolution->table->name->catalog === null,
                'The projection requires a resolved, complete base table declaration.',
            );
        }
        $this->table = $resolution?->table;
        Check::input(
            $this->table === null || $this->table->name->schema !== null || $operation->context->declarationSchema->value !== SessionDatabase::UNNAMED,
            'The table must resolve to a named MySQL database.',
        );
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
        Check::input($column->type instanceof Integral && $column->type->kind === IntegralKind::Int && !$column->type->unsigned(), 'The bound column must have the signed 32-bit integer type.');
        $key = spl_object_id($column);
        if (!isset($this->slots[$key])) {
            $this->slots[$key] = count($this->names);
            $this->names[] = new Identifier($column->name->value);
        }

        return new ColumnReference($this->slots[$key]);
    }

    /**
     * Identifies the analysis occurrence that this binding owns.
     */
    public function relation(): ?Relation
    {
        return $this->source;
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
