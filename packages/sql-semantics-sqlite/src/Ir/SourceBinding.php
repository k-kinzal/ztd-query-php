<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\From\SourceDeclarations;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;

/**
 * Allocates column slots independently for each local occurrence.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SourceBinding
{
    /**
     * @var list<array{TableInput|MutationTarget, Table|LocalSource}>
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
     * @param non-empty-list<Identifier>|null $localColumns The slots of an enclosing declaration or recursive arm
     * @param array{CommonTable, LocalSource}|null $localDefinition The exact compiled WITH definition
     */
    public function __construct(
        private readonly BoundStatement $operation,
        private readonly ?Relation $source,
        private readonly ?array $localColumns = null,
        ?array $localDefinition = null,
    ) {
        $this->tables = $localColumns === null ? (new SourceDeclarations())->bind($operation, $source, $localDefinition) : [];
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
     * Resolves a scalar name use, including a combined FULL join value.
     * @throws InvalidConstruction When an operand has no visible binding
     */
    public function scalarValue(Scalar $expression): ColumnReference|Coalesce
    {
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::input($resolution instanceof ResolvedColumn, 'The column must resolve to the projection source.');

        return $this->value($resolution);
    }

    /**
     * Lowers a physical slot or the ordered value of a merged input field.
     * @throws InvalidConstruction When one of the field inputs is outside the local scope
     */
    public function value(ResolvedColumn $resolution): ColumnReference|Coalesce
    {
        $merge = $resolution->merge;
        if ($merge === null) {
            return $this->resolved($resolution);
        }
        Check::input($resolution->depth === 0, 'A merged column must belong to the local query.');

        return new Coalesce([
            new CoalesceArgument($this->value($merge->left)),
            new CoalesceArgument($this->value($merge->right)),
        ]);
    }

    /**
     * Allocates the occurrence slot of an already resolved projection field.
     *
     * @throws InvalidConstruction When the field is outside the checked input scope
     */
    public function resolved(ResolvedColumn $resolution): ColumnReference
    {
        Check::input(
            $resolution->depth === 0 && $resolution->merge === null,
            'The column must resolve to the projection source.'
        );
        if ($this->localColumns !== null) {
            Check::input($resolution->relation === $this->source, 'The column must resolve to the local input occurrence.');

            return $this->localSlot($resolution, $this->localColumns, 0);
        }
        $column = $resolution->declaration();
        foreach ($this->tables as $index => [$occurrence, $table]) {
            if ($resolution->relation === $occurrence) {
                if ($table instanceof LocalSource) {
                    return $this->localSlot($resolution, $table->columns, $index);
                }
                Check::input($column !== null, 'The column must be an explicit member of the projection source.');
                return new ColumnReference($this->slot($index, $column), $index);
            }
        }

        throw new InvalidConstruction('The column must resolve to a local base-table occurrence.');
    }

    /**
     * Resolves a column by its name in the verified declaration at one occurrence.
     * @param non-empty-list<Identifier> $columns The declared names in native order
     * @throws InvalidConstruction When the resolution has no checked local slot
     */
    public function localSlot(ResolvedColumn $resolution, array $columns, int $source): ColumnReference
    {
        $name = $resolution->slot->name;
        Check::input($name !== null, 'The column must resolve to a named local slot.');
        $key = (new Identifier($name->value))->key();
        foreach ($columns as $index => $column) {
            if ($column->key() === $key) {
                return new ColumnReference($index, $source);
            }
        }

        throw new InvalidConstruction('The column must belong to the local input.');
    }

    /**
     * Resolves a writable declared name in the single mutation target.
     */
    public function write(Name $name): int
    {
        [$occurrence, $table] = $this->tables[0] ?? [null, null];
        Check::invariant($occurrence instanceof MutationTarget && $table instanceof Table, 'A written slot belongs to a bound mutation target.');
        $matches = $table->matchingColumns($name->value, Comparison::AsciiInsensitive);
        Check::input(
            count($matches) === 1 && in_array($matches[0], $table->columns, true) && !$matches[0]->generated,
            'An assigned name must resolve to a writable declared column.',
        );

        return $this->slot(0, $matches[0]);
    }

    /**
     * Allocates the writable fields of an implicit target list in declaration order.
     * @return list<ColumnReference>
     */
    public function implicitWrites(): array
    {
        [$occurrence, $table] = $this->tables[0] ?? [null, null];
        Check::invariant($occurrence instanceof MutationTarget && $table instanceof Table, 'An implicit write requires a bound mutation target.');
        $writes = [];
        foreach ($table->columns as $column) {
            if (!$column->generated) {
                $writes[] = new ColumnReference($this->slot(0, $column));
            }
        }

        return $writes;
    }

    /**
     * Returns a Boolean constant once lookup has established its literal role.
     */
    public function constantTruth(TruthWord $word): ?bool
    {
        return $this->operation->facts->scalar($word)->resolution === null ? $word->value : null;
    }

    /**
     * Records a resolved column once within its occurrence.
     */
    public function slot(int $occurrence, Column $column): int
    {
        $table = $this->tables[$occurrence][1] ?? null;
        Check::input($table instanceof Table && in_array($column, $table->columns, true), 'The column must be an explicit member of the projection source.');
        $key = spl_object_id($column);
        if (!isset($this->slots[$occurrence][$key])) {
            $this->slots[$occurrence][$key] = count($this->names[$occurrence] ?? []);
            $this->names[$occurrence][] = new Identifier($column->name->value);
        }

        return $this->slots[$occurrence][$key];
    }

    /**
     * Exports the external identity and consumed column names.
     * @throws InvalidConstruction When the occurrence lacks an admitted external kind
     */
    public function source(int $index = 0): ?TableSource
    {
        $table = $this->tables[$index][1] ?? null;

        Check::invariant($table === null || $table instanceof Table, 'This occurrence has an external table identity.');

        if ($table === null) {
            return null;
        }

        $kind = match ($table->kind) {
            RelationKind::BaseTable => ExternalRelationKind::BaseTable,
            RelationKind::View => ExternalRelationKind::View,
            RelationKind::MaterializedView, RelationKind::ForeignTable, RelationKind::Sequence
                => throw new InvalidConstruction('An external SQLite occurrence requires an ordinary table or view.'),
        };

        return new TableSource(
            new Identifier(($table->name->schema ?? $this->operation->context->declarationSchema)->value),
            new Identifier($table->name->name->value),
            $this->names[$index] ?? [],
            $kind,
        );
    }

    /**
     * Exports either the resolved external table or the shared local definition.
     */
    public function input(int $index = 0): TableSource|LocalSource|null
    {
        $binding = $this->tables[$index][1] ?? null;

        return $binding instanceof LocalSource ? $binding : $this->source($index);
    }
}
