<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A resolved named PostgreSQL B-tree definition.
 *
 * @visibility public
 * @example Creating a B-tree on a resolved integer heading
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $table = new \SqlSemantics\Platform\PostgreSql\Ir\TableSource(new $name('public'), new $name('orders'), [new $name('id')]);
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
     * Validates both operand lists against the fixed heading and index creation policies.
     *
     * @param list<IndexKey> $keys
     * @param list<IncludedColumn> $included
     */
    public function __construct(
        public readonly TableSource $table,
        public readonly Identifier $name,
        array $keys,
        array $included = [],
        public readonly IndexPresence $presence = IndexPresence::Vacant,
        public readonly bool $ifNotExists = false,
        public readonly bool $unique = false,
        public readonly bool $nullsNotDistinct = false,
        public readonly bool $concurrently = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly NullIndexPredicate|IndexTruthTest|IndexBooleanPredicate|null $predicate = null,
    ) {
        Check::input($table->kind === ExternalRelationKind::BaseTable && !IndexSchemas::system($table->schema->value)
            && !IndexSchemas::sessionInternal($table->schema->value), 'A B-tree needs a resolved ordinary application or temporary table.');
        Check::input($presence === IndexPresence::Vacant || $ifNotExists, 'An occupied index name requires IF NOT EXISTS.');
        Check::input($table->table->value !== $name->value || $presence === IndexPresence::Occupied, 'An indexed table already occupies its own name.');
        $checkedKeys = Check::listOf($keys, IndexKey::class, 'A B-tree needs a nonempty ordered list of key occurrences.', 1);
        $checkedIncluded = Check::listOf($included, IncludedColumn::class, 'B-tree payload positions must be included column occurrences.');
        Check::input(count($checkedKeys) + count($checkedIncluded) <= 32, 'A B-tree supports at most 32 key and covering occurrences.');
        foreach ($table->columnKinds as $kind) {
            Check::input($kind !== ColumnKind::Text, 'A B-tree in this profile requires integer or Boolean physical column types.');
        }
        foreach ($checkedKeys as $key) {
            Check::input(isset($table->columns[$key->slot]), 'A B-tree key must refer to a column of the indexed table.');
        }
        foreach ($checkedIncluded as $column) {
            Check::input(isset($table->columns[$column->slot]), 'An included position must refer to a column of the indexed table.');
        }
        if ($predicate !== null) {
            IndexCondition::check($table, $predicate);
        }
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
            $keys[] = $this->table->columns[$key->slot]->quoted() . ' ' . $key->order->value;
        }
        $included = [];
        foreach ($this->included as $column) {
            $included[] = $this->table->columns[$column->slot]->quoted();
        }

        return 'CREATE ' . ($this->unique ? 'UNIQUE ' : '') . 'INDEX ' . ($this->concurrently ? 'CONCURRENTLY ' : '')
            . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->name->quoted()
            . ' ON ' . ($this->table->only ? 'ONLY ' : '') . $this->table->schema->quoted() . '.' . $this->table->table->quoted()
            . ' USING btree (' . implode(', ', $keys) . ')'
            . ($included === [] ? '' : ' INCLUDE (' . implode(', ', $included) . ')')
            . ($this->nullsNotDistinct ? ' NULLS NOT DISTINCT' : '')
            . ($this->predicate === null ? '' : IndexCondition::sql($this->table, $this->predicate));
    }
}
