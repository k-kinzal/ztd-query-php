<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Allocates local slots for a resolved table occurrence.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
            Check::input(($source instanceof TableInput && $source->sample === null) || $source instanceof TargetTable, 'The projection source must be an ordinary table input.');
            Check::input(
                $operation->context->relationNames === Comparison::Sensitive
                    && $operation->context->columnNames === Comparison::Sensitive,
                'The declaration context must use PostgreSQL name comparison.',
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
        Check::input($resolution instanceof ResolvedColumn, 'The column must resolve to the projection source.');

        return $this->resolved($resolution);
    }

    /**
     * Allocates the occurrence slot of an already resolved projection field.
     */
    public function resolved(ResolvedColumn $resolution): ColumnReference
    {
        Check::input(
            $resolution->relation === $this->source && $resolution->depth === 0,
            'The column must resolve to the projection source.'
        );
        $column = $resolution->declaration();
        Check::input($column !== null, 'The column must be an explicit member of the projection source.');

        return $this->slotFor($column);
    }

    /**
     * Allocates a slot for a checked integer member of this table.
     */
    public function slotFor(Column $column): ColumnReference
    {
        Check::input($this->table !== null && in_array($column, $this->table->columns, true), 'The column must be an explicit member of the projection source.');
        Check::input($column->type === Builtin::Int4, 'The bound column must have the signed 32-bit integer type.');
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
        if ($this->table === null || (!$this->source instanceof TableInput && !$this->source instanceof TargetTable)) {
            return null;
        }

        return new TableSource(
            new Identifier(($this->table->name->schema ?? $this->operation->context->declarationSchema)->value),
            new Identifier($this->table->name->name->value),
            $this->names,
            $this->source->table->only,
        );
    }

    /**
     * Resolves the explicit write slots in their supplied order.
     *
     * @param list<string> $names
     */
    public function writeTarget(array $names): TableSource
    {
        $target = $this->source();
        Check::input($target !== null, 'An INSERT requires a resolved write target.');
        $columns = [];
        foreach ($names as $name) {
            $columns[] = new Identifier($this->writableColumn($name)->name->value);
        }

        return new TableSource($target->schema, $target->table, $columns);
    }

    /**
     * Resolves a name to a writable explicit member with the signed integer role.
     */
    public function writableColumn(string $name): Column
    {
        Check::input($this->table !== null, 'A write requires a resolved base table.');
        $matches = $this->table->matchingColumns($name, $this->operation->context->columnNames);
        Check::input(count($matches) === 1 && in_array($matches[0], $this->table->columns, true), 'A write column must resolve to a declared target slot.');
        $column = $matches[0];
        Check::input(!$column->generated && !$column->identity && $column->type === Builtin::Int4, 'A write column must be a writable signed 32-bit integer.');

        return $column;
    }

    /**
     * Reads the resolved scalar type for this analysis occurrence.
     */
    public function typeOf(Scalar $expression): TypeFact
    {
        return $this->operation->facts->scalar($expression)->type;
    }
}
