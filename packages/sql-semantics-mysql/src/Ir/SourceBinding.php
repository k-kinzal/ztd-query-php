<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralSlot;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\From\VarcharLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * Allocates local slots for a resolved table occurrence.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SourceBinding
{
    private readonly ExternalRelationKind $kind;

    /**
     * @var list<Identifier> Names of the consumed columns
     */
    private array $names = [];

    /**
     * @var list<ColumnKind|IntegralSlot|Varchar> The native type of each consumed column
     */
    private array $columnTypes = [];

    /**
     * @var array<int, int> Slot by analysis declaration identity
     */
    private array $slots = [];

    private readonly ?Table $table;

    /**
     * Resolves the source from the analysis before any slots can be allocated.
     * @throws InvalidConstruction When the relation has no admitted native read role
     */
    public function __construct(private readonly BoundStatement $operation, private readonly ?Relation $source)
    {
        $resolution = null;
        $kind = ExternalRelationKind::BaseTable;
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
                    && $resolution->table->name->catalog === null,
                'The source requires a resolved, complete local relation declaration.',
            );
            $kind = match ($resolution->table->kind) {
                RelationKind::BaseTable => $resolution->table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable,
                RelationKind::View => ExternalRelationKind::View,
                RelationKind::MaterializedView, RelationKind::ForeignTable, RelationKind::Sequence => throw new InvalidConstruction('The source requires an admitted external relation kind.'),
            };
            Check::input(!$source instanceof WriteTarget || $kind->isTable(), 'A write requires a resolved base table.');
        }
        $this->table = $resolution?->table;
        $this->kind = $kind;
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
        Check::input($resolution instanceof ResolvedColumn, 'The column must resolve to the projection source.');

        return $this->resolved($resolution);
    }

    /**
     * Resolves a native signed read in the owning occurrence.
     */
    public function membershipReference(Scalar $expression): ColumnReference|IntegralColumnReference
    {
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::input($resolution instanceof ResolvedColumn, 'A membership column must resolve to its input.');
        return $this->membershipSlot($resolution);
    }

    /**
     * Allocates the exact native storage and nullability for a signed comparison read.
     */
    public function membershipSlot(ResolvedColumn $resolution): ColumnReference|IntegralColumnReference
    {
        $column = $this->declared($resolution);
        Check::input($column->type instanceof Integral && !$column->type->unsigned(), 'A membership column requires signed integral storage.');
        if ($column->type->kind === IntegralKind::Int) {
            return $this->slotFor($column);
        }
        $storage = match ($column->type->kind) {
            IntegralKind::TinyInt => IntegerStorage::Tiny,
            IntegralKind::SmallInt => IntegerStorage::Small,
            IntegralKind::MediumInt => IntegerStorage::Medium,
            IntegralKind::BigInt => IntegerStorage::Wide,
        };
        Check::input($column->nullability !== Nullability::Dependent, 'A membership column needs known nullability.');

        return new IntegralColumnReference($this->allocate($column, new IntegralSlot($storage, $column->nullability !== Nullability::NotNull)));
    }

    /**
     * Resolves a scalar field with the native kind required by a typed read.
     */
    public function field(Scalar $expression): ColumnReference|TextColumnReference
    {
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::input($resolution instanceof ResolvedColumn, 'The column must resolve to the projection source.');

        return $this->projected($resolution);
    }

    /**
     * Allocates the occurrence slot of an already resolved projection field.
     */
    public function resolved(ResolvedColumn $resolution): ColumnReference
    {
        return $this->slotFor($this->declared($resolution));
    }

    /**
     * Resolves the exact native role of a field in the containing projection.
     */
    public function projected(ResolvedColumn $resolution): ColumnReference|TextColumnReference
    {
        $column = $this->declared($resolution);
        if (!$column->type instanceof Character) {
            return $this->slotFor($column);
        }

        return new TextColumnReference($this->allocate($column, (new VarcharLowering())->lower($column)));
    }

    /**
     * Obtains the actual declaration belonging to this source occurrence.
     */
    public function declared(ResolvedColumn $resolution): Column
    {
        Check::input(
            $resolution->relation === $this->source && $resolution->depth === 0,
            'The column must resolve to the projection source.'
        );
        $column = $resolution->declaration();
        Check::input($column !== null, 'The column must be an explicit member of the projection source.');

        return $column;
    }

    /**
     * Allocates a slot for a checked integer member of this table.
     */
    public function slotFor(Column $column): ColumnReference
    {
        Check::input($column->type instanceof Integral && $column->type->kind === IntegralKind::Int && !$column->type->unsigned(), 'The bound column must have the signed 32-bit integer type.');

        return new ColumnReference($this->allocate($column, $this->signedKind($column)));
    }

    /**
     * Allocates one name and type together for the resolved physical position.
     */
    public function allocate(Column $column, ColumnKind|IntegralSlot|Varchar $native): int
    {
        Check::input($this->table !== null && in_array($column, $this->table->columns, true), 'The column must be an explicit member of the projection source.');
        $key = spl_object_id($column);
        if (!isset($this->slots[$key])) {
            $this->slots[$key] = count($this->names);
            $this->names[] = new Identifier($column->name->value);
            $this->columnTypes[] = $native;
        }

        return $this->slots[$key];
    }

    /**
     * Exposes a checked ordinary heading to merged join lowering.
     * @return array<array-key, Column>
     */
    public function mergedHeading(): array
    {
        Check::input($this->table !== null && $this->kind->isTable() && $this->table->implicit === [], 'A merged join requires complete visible base-table headings.');
        $heading = [];
        foreach ($this->table->columns as $column) {
            $name = $column->name->value;
            $key = Comparison::AsciiInsensitive->fold($name);
            Check::input(preg_match('/\A[\x01-\x7f]+\z/D', $name) === 1 && !isset($heading[$key]), 'A merged join requires distinct ASCII field names.');
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
        return $this->table === null ? null : new TableSource(
            new Identifier(($this->table->name->schema ?? $this->operation->context->declarationSchema)->value),
            new Identifier($this->table->name->name->value),
            $this->names,
            $this->columnTypes,
            $this->kind,
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
        Check::input($target !== null && $target->kind->isTable(), 'An INSERT requires a resolved write target.');
        $columns = [];
        $types = [];
        foreach ($names as $name) {
            [$column, $type] = $this->writableColumn($name);
            $columns[] = new Identifier($column->name->value);
            $types[] = $type;
        }

        return new TableSource($target->schema, $target->table, $columns, $types, $target->kind);
    }

    /**
     * Resolves a writable field for a typed single-table assignment.
     */
    public function updateField(string $name): ColumnReference|TextColumnReference
    {
        [$column, $type] = $this->writableColumn($name);
        $slot = $this->allocate($column, $type);

        return $type instanceof Varchar ? new TextColumnReference($slot) : new ColumnReference($slot);
    }

    /**
     * Resolves a destination and its approved native writing type together.
     *
     * @return array{Column, ColumnKind|Varchar}
     */
    public function writableColumn(string $name): array
    {
        $column = $this->writeDeclaration($name);
        return $column->type instanceof Character
            ? [$column, (new VarcharLowering())->lower($column)]
            : [$this->signedWrite($column), $this->signedKind($column)];
    }

    /**
     * Preserves exact declared nullability in a resolved native signed role.
     */
    public function signedKind(Column $column): ColumnKind
    {
        Check::input($column->nullability !== Nullability::Dependent, 'A native signed slot requires known column nullability.');

        return $column->nullability === Nullability::NotNull ? ColumnKind::RequiredSignedInt : ColumnKind::SignedInt;
    }

    /**
     * Finds the exact physical declaration shared by native write roles.
     */
    public function writeDeclaration(string $name): Column
    {
        Check::input($this->table !== null && $this->kind->isTable(), 'A write requires a resolved base table.');
        $matches = $this->table->matchingColumns($name, $this->operation->context->columnNames);
        Check::input(count($matches) === 1 && in_array($matches[0], $this->table->columns, true), 'A write column must resolve to a declared target slot.');
        return $matches[0];
    }

    /**
     * Restricts a writable field to the signed role.
     */
    public function signedWrite(Column $column): Column
    {
        Check::input(!$column->generated && $column->type instanceof Integral && $column->type->kind === IntegralKind::Int && !$column->type->unsigned(), 'A write column must be a writable signed 32-bit integer.');

        return $column;
    }
}
