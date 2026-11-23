<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
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
    private readonly ?ExternalRelationKind $kind;

    /**
     * @var list<Identifier> Names of the consumed columns
     */
    private array $names = [];

    /**
     * @var list<ColumnKind> Native kind at each consumed coordinate
     */
    private array $kinds = [];

    /**
     * @var array<int, int> Slot by analysis declaration identity
     */
    private array $slots = [];

    private readonly ?Table $table;

    /**
     * Resolves the source from the analysis before any slots can be allocated.
     *
     * @throws InvalidConstruction When the resolved relation kind has no admitted source role
     */
    public function __construct(private readonly BoundStatement $operation, private readonly ?Relation $source)
    {
        $resolution = null;
        $kind = null;
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
                    && $resolution->table->name->catalog === null,
                'The source requires a resolved, complete local relation declaration.',
            );
            $kind = match ($resolution->table->kind) {
                RelationKind::BaseTable => ExternalRelationKind::BaseTable,
                RelationKind::View => ExternalRelationKind::View,
                RelationKind::MaterializedView => ExternalRelationKind::MaterializedView,
                RelationKind::ForeignTable, RelationKind::Sequence => throw new InvalidConstruction('The source requires an admitted external relation kind.'),
            };
            Check::input(!$source instanceof TargetTable || $kind === ExternalRelationKind::BaseTable, 'A write requires a resolved base table.');
        }
        $this->table = $resolution?->table;
        $this->kind = $kind;
    }

    /**
     * Allocates or reuses a local slot for the exact resolved column.
     */
    public function reference(Scalar $expression): ColumnReference
    {
        $field = $this->field($expression);
        Check::input($field instanceof ColumnReference, 'The operand requires an int4 input slot.');

        return $field;
    }

    /**
     * Binds a scalar field to its exact occurrence and native column role.
     */
    public function field(Scalar $expression): ColumnReference|TextColumnReference|BooleanColumnReference|WideIntegerColumnReference
    {
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::input($resolution instanceof ResolvedColumn, 'The column must resolve to the projection source.');

        return $this->resolved($resolution);
    }

    /**
     * Allocates the occurrence slot of an already resolved projection field.
     */
    public function resolved(ResolvedColumn $resolution): ColumnReference|TextColumnReference|BooleanColumnReference|WideIntegerColumnReference
    {
        Check::input(
            $resolution->relation === $this->source && $resolution->depth === 0,
            'The column must resolve to the projection source.'
        );
        $column = $resolution->declaration();
        Check::input($column !== null, 'The column must be an explicit member of the projection source.');

        return match ($column->type) {
            Builtin::Bool => new BooleanColumnReference($this->allocate($column)),
            Builtin::Int8 => new WideIntegerColumnReference($this->allocate($column)),
            Builtin::Text => new TextColumnReference($this->allocate($column)),
            default => $this->slotFor($column),
        };
    }

    /**
     * Allocates a slot for a checked integer member of this table.
     */
    public function slotFor(Column $column): ColumnReference
    {
        Check::input($column->type === Builtin::Int4, 'The bound column must have the signed 32-bit integer type.');

        return new ColumnReference($this->allocate($column));
    }

    /**
     * Allocates the declared name and its kind together at the same coordinate.
     * @throws InvalidConstruction When the declared kind has no closed physical slot
     */
    public function allocate(Column $column): int
    {
        Check::input($this->table !== null && in_array($column, $this->table->columns, true), 'The column must be an explicit member of the projection source.');
        $kind = match ($column->type) {
            Builtin::Int4 => ColumnKind::Int4,
            Builtin::Text => ColumnKind::Text,
            Builtin::Bool => ColumnKind::Boolean,
            Builtin::Int8 => ColumnKind::Int8,
            default => throw new InvalidConstruction('The declared column has no checked native slot kind.'),
        };
        $key = spl_object_id($column);
        if (!isset($this->slots[$key])) {
            $name = new Identifier($column->name->value);
            $this->slots[$key] = count($this->names);
            $this->names[] = $name;
            $this->kinds[] = $kind;
        }

        return $this->slots[$key];
    }

    /**
     * Indexes the table's declared heading in its original order.
     *
     * @return array<string, Column>
     */
    public function mergedHeading(): array
    {
        Check::input($this->table !== null, 'A merged join needs a declared input heading.');
        $heading = [];
        foreach ($this->table->columns as $column) {
            $key = ':' . $column->name->value;
            Check::input(!isset($heading[$key]), 'A merged input heading cannot repeat a field name.');
            $heading[$key] = $column;
        }

        return $heading;
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
        if ($this->table === null || $this->kind === null || (!$this->source instanceof TableInput && !$this->source instanceof TargetTable)) {
            return null;
        }

        return new TableSource(
            new Identifier(($this->table->name->schema ?? $this->operation->context->declarationSchema)->value),
            new Identifier($this->table->name->name->value),
            $this->names,
            $this->source->table->only,
            $this->kinds,
            $this->kind,
        );
    }

    /**
     * Resolves a declared writable member in the admitted native roles.
     */
    public function writableColumn(string $name): Column
    {
        Check::input($this->table !== null, 'A write requires a resolved base table.');
        $matches = $this->table->matchingColumns($name, $this->operation->context->columnNames);
        Check::input(count($matches) === 1 && in_array($matches[0], $this->table->columns, true), 'A write column must resolve to a declared target slot.');
        $column = $matches[0];
        Check::input(
            !$column->generated && !$column->identity && in_array($column->type, [Builtin::Int4, Builtin::Int8, Builtin::Text, Builtin::Bool], true),
            'A write column must be a writable signed integer or an admitted text or Boolean field.',
        );

        return $column;
    }

    /**
     * Allocates the native role of a checked mutation destination.
     */
    public function writeReference(string $name): ColumnReference|TextColumnReference|BooleanColumnReference|WideIntegerColumnReference
    {
        $column = $this->writableColumn($name);

        return match ($column->type) {
            Builtin::Int8 => new WideIntegerColumnReference($this->allocate($column)),
            Builtin::Text => new TextColumnReference($this->allocate($column)),
            Builtin::Bool => new BooleanColumnReference($this->allocate($column)),
            default => $this->slotFor($column),
        };
    }

    /**
     * Reads the resolved scalar type for this analysis occurrence.
     */
    public function typeOf(Scalar $expression): TypeFact
    {
        return $this->operation->facts->scalar($expression)->type;
    }
}
