<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An ordinary resolved native InnoDB B-tree index declaration.
 *
 * @visibility public
 * @example Qualifying an index's owning table
 *     $table = new \SqlSemantics\Platform\MySql\Ir\TableSource(new \SqlSemantics\Platform\MySql\Ir\Identifier('app'), new \SqlSemantics\Platform\MySql\Ir\Identifier('accounts'), [new \SqlSemantics\Platform\MySql\Ir\Identifier('balance')]);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex($table, new \SqlSemantics\Platform\MySql\Ir\Identifier('ix_balance'), [new \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexColumn(0)]))->toString() // => 'CREATE INDEX `ix_balance` ON `app`.`accounts` (`balance`)'
 */
final class CreateIndex implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<IndexColumn> Index parts in native key order
     */
    public readonly array $columns;

    /**
     * Closes the native index definition against its resolved table.
     *
     * @param list<IndexColumn> $columns The ordered index parts
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly Identifier $name,
        array $columns,
        public readonly bool $unique = false,
        public readonly bool $visible = true,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly bool $ownerHasIdentityKey = false,
    ) {
        Check::input($target->kind->isTable(), 'CREATE INDEX requires a resolved persistent or temporary InnoDB table.');
        Check::input(count($target->columns) <= 1017, 'An InnoDB index owner can declare at most 1017 fields.');
        $case = new NativeIdentifierCase();
        Check::input(
            preg_match('/\A.{1,64}(?<! )\z/us', $name->value) === 1 && $case->fold($name->value) !== 'primary',
            'CREATE INDEX requires an index name of 1 to 64 characters other than PRIMARY and without a trailing space.'
        );
        $parts = Check::listOf($columns, IndexColumn::class, 'CREATE INDEX needs a nonempty ordered list of index parts.', 1);
        Check::input(count($parts) <= 16, 'An InnoDB secondary index can have at most 16 key parts.');
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        Check::input($visible || !$legacy, 'This MySQL release has no invisible index role.');
        $seen = [];
        $allowsNull = false;
        foreach ($parts as $part) {
            $type = $target->columnTypes[$part->slot] ?? null;
            Check::input(
                $type instanceof ColumnKind,
                'Each index part must select a declared signed column of its owning table.'
            );
            $field = $case->fold($target->columns[$part->slot]->value);
            Check::input(!isset($seen[$field]), 'CREATE INDEX cannot repeat a key column.');
            Check::input(!$part->descending || !$legacy, 'This MySQL release does not store descending index parts.');
            $seen[$field] = true;
            $allowsNull = $allowsNull || $type->nullable();
        }
        Check::input($visible || !$unique || $ownerHasIdentityKey || $allowsNull, 'An implicit primary index must be visible.');
        $this->columns = $parts;
    }

    /**
     * Identifies the native storage and release used to interpret the index.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/btree-index';
    }

    /**
     * Renders the resolved table and its complete ordered index key.
     */
    public function toString(): string
    {
        $parts = [];
        foreach ($this->columns as $column) {
            $parts[] = $this->target->columns[$column->slot]->quoted() . ($column->descending ? ' DESC' : '');
        }

        return 'CREATE ' . ($this->unique ? 'UNIQUE ' : '') . 'INDEX ' . $this->name->quoted()
            . ' ON ' . $this->target->schema->quoted() . '.' . $this->target->table->quoted()
            . ' (' . implode(', ', $parts) . ')' . ($this->visible ? '' : ' INVISIBLE');
    }
}
