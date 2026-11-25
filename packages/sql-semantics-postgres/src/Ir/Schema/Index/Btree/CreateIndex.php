<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexField;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A resolved named PostgreSQL B-tree definition.
 *
 * @visibility public
 * @example Creating a B-tree on a resolved integer heading
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $owner = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget(new $name('public'), new $name('orders'));
 *     $table = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading($owner, [new $name('id')], [\SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind::Int4]);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex($table, new $name('idx'), [new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexKey(0)]))->name->value // => 'idx'
 */
final class CreateIndex implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<IndexKey> Key occurrences in native order
     */
    public readonly array $keys;

    /**
     * @var list<IncludedColumn> Covering occurrences in native order
     */
    public readonly array $included;

    /**
     * The exact ordinary table coordinate of the index.
     */
    public readonly IndexTarget $table;

    /**
     * @var array<int, IndexField> The consumed fields keyed by original physical slot
     */
    public readonly array $bindings;

    /**
     * Validates both operand lists against the fixed heading and index creation policies.
     *
     * @param list<IndexKey> $keys
     * @param list<IncludedColumn> $included
     */
    public function __construct(
        IndexHeading $heading,
        public readonly Identifier $name,
        array $keys,
        array $included = [],
        public readonly IndexPresence $presence = IndexPresence::Vacant,
        public readonly bool $ifNotExists = false,
        public readonly bool $unique = false,
        public readonly bool $nullsNotDistinct = false,
        public readonly bool $concurrently = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly NullIndexPredicate|IndexTruthTest|IndexBooleanColumn|IndexBooleanPredicate|null $predicate = null,
    ) {
        Check::input($presence === IndexPresence::Vacant || $ifNotExists, 'An occupied index name requires IF NOT EXISTS.');
        Check::input($heading->target->table->value !== $name->value || $presence === IndexPresence::Occupied, 'An indexed table already occupies its own name.');
        $checkedKeys = Check::listOf($keys, IndexKey::class, 'A B-tree needs a nonempty ordered list of key occurrences.', 1);
        $checkedIncluded = Check::listOf($included, IncludedColumn::class, 'B-tree payload positions must be included column occurrences.');
        Check::input(count($checkedKeys) + count($checkedIncluded) <= 32, 'A B-tree supports at most 32 key and covering occurrences.');
        $resolvedFields = [];
        foreach ($checkedKeys as $key) {
            Check::input(isset($heading->columns[$key->slot]), 'A B-tree key must refer to a column of the indexed table.');
            $resolvedFields[$key->slot] = $heading->field($key->slot);
        }
        foreach ($checkedIncluded as $column) {
            Check::input(isset($heading->columns[$column->slot]), 'An included position must refer to a column of the indexed table.');
            $resolvedFields[$column->slot] = $heading->field($column->slot);
        }
        if ($predicate !== null) {
            $resolvedFields += IndexCondition::bindings($heading, $predicate);
        }
        ksort($resolvedFields, SORT_NUMERIC);
        $this->table = $heading->target;
        $this->bindings = $resolvedFields;
        $this->keys = $checkedKeys;
        $this->included = $checkedIncluded;
    }

    /**
     * Identifies the fixed native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits every checked key and covering occurrence under the represented PostgreSQL policies.
     */
    public function toString(): string
    {
        $keys = [];
        foreach ($this->keys as $key) {
            $keys[] = $this->bindings[$key->slot]->name->quoted() . ' ' . $key->order->value;
        }
        $included = [];
        foreach ($this->included as $column) {
            $included[] = $this->bindings[$column->slot]->name->quoted();
        }

        return 'CREATE ' . ($this->unique ? 'UNIQUE ' : '') . 'INDEX ' . ($this->concurrently ? 'CONCURRENTLY ' : '')
            . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->name->quoted()
            . ' ON ' . ($this->table->only ? 'ONLY ' : '') . $this->table->schema->quoted() . '.' . $this->table->table->quoted()
            . ' USING btree (' . implode(', ', $keys) . ')'
            . ($included === [] ? '' : ' INCLUDE (' . implode(', ', $included) . ')')
            . ($this->nullsNotDistinct ? ' NULLS NOT DISTINCT' : '')
            . ($this->predicate === null ? '' : IndexCondition::sql($this->bindings, $this->predicate));
    }
}
