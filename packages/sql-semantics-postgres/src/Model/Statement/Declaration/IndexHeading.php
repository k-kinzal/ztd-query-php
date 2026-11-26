<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexField;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Original table declarations used when checking a PostgreSQL B-tree.
 * @visibility public
 * @example Binding one native key field from its declaring heading
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $owner = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget(new $name('app'), new $name('items'));
 *     $kind = \SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind::Int4;
 *     (new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading($owner, [new $name('id')], [$kind]))->field(0)->name->value // => 'id'
 */
final class IndexHeading
{
    use Snapshot;

    /**
     * @var list<Identifier> Names at original physical slots
     */
    public readonly array $columns;

    /**
     * @var list<ColumnKind|null> Checked native operand kinds, with null for out-of-profile types
     */
    public readonly array $columnKinds;

    /**
     * @var array<int, TextCollation> Resolved declared comparison defaults for selected physical text slots
     */
    public readonly array $columnCollations;

    /**
     * Fixes the original physical layout before checking any consumed fields.
     * @param list<Identifier> $columns Declared physical names
     * @param array<array-key, mixed> $types One resolved kind per physical slot
     * @param array<array-key, mixed> $collations Known native text collation defaults by slot
     */
    public function __construct(
        public readonly IndexTarget $target,
        array $columns,
        array $types,
        ExternalRelationKind $kind = ExternalRelationKind::BaseTable,
        array $collations = [],
    ) {
        Check::input($kind === ExternalRelationKind::BaseTable, 'A B-tree needs a resolved ordinary application or temporary table.');
        $names = Check::listOf($columns, Identifier::class, 'A B-tree heading needs exact physical column names.');
        $seen = [];
        foreach ($names as $name) {
            Check::input(!isset($seen[$name->value]), 'A B-tree heading cannot repeat a physical column name.');
            $seen[$name->value] = true;
        }
        Check::input(array_is_list($types) && count($types) === count($names), 'A B-tree heading needs one kind per physical slot.');
        $owned = [];
        foreach ($types as $type) {
            Check::input($type === null || $type instanceof ColumnKind, 'A B-tree heading needs checked physical type facts.');
            $owned[] = $type;
        }
        $defaults = [];
        foreach ($collations as $slot => $collation) {
            Check::input(
                is_int($slot) && ($owned[$slot] ?? null) === ColumnKind::Text && $collation instanceof TextCollation,
                'A declared B-tree collation must resolve to a physical text field.'
            );
            $defaults[$slot] = $collation;
        }
        $this->columnCollations = $defaults;
        $this->columns = $names;
        $this->columnKinds = $owned;
    }

    /**
     * Binds one used physical position to the completed operand type.
     */
    public function field(int $slot): IndexField
    {
        $kind = $this->columnKinds[$slot] ?? null;
        Check::input(
            isset($this->columns[$slot]) && $kind !== null,
            'A B-tree in this profile requires integer, Boolean or text physical column types.'
        );

        return new IndexField($this->columns[$slot], $kind);
    }
}
