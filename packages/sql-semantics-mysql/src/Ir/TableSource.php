<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\From\IntegralSlot;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A schema-qualified external source and the named column slots it supplies.
 *
 * The slots identify inputs. Evaluation supplies the corresponding table
 * or view in the resolved namespace and its columns, with their MySQL row values.
 *
 * @visibility public
 * @example Identifying a column of an external table
 *     $source = new \SqlSemantics\Platform\MySql\Ir\TableSource(new \SqlSemantics\Platform\MySql\Ir\Identifier('main'), new \SqlSemantics\Platform\MySql\Ir\Identifier('accounts'), [new \SqlSemantics\Platform\MySql\Ir\Identifier('id')]);
 *     $source->columns[0]->value // => 'id'
 */
final class TableSource
{
    use Snapshot;

    /**
     * @var list<Identifier> The named input slots
     */
    public readonly array $columns;

    /**
     * @var list<ColumnKind|IntegralSlot|Varchar> The native type at each corresponding slot
     */
    public readonly array $columnTypes;

    /**
     * Establishes a fixed external identity and an ordered list of unique column slots.
     *
     * @param list<Identifier> $columns The names at those slots
     * @param array<array-key, mixed> $columnTypes The types in slot order, or empty for signed INT slots
     * @param bool $materializedView Whether unaliased view fields use the written references as result headings
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $columns,
        array $columnTypes = [],
        public readonly ExternalRelationKind $kind = ExternalRelationKind::BaseTable,
        public readonly bool $materializedView = false,
    ) {
        $names = Check::listOf($columns, Identifier::class, 'A table source needs an ordered list of identifier slots.');
        foreach ([$schema, $table] as $object) {
            Check::input(strpbrk($object->value, '/\\.') === false, 'A database or table name excludes path and dot separators.');
        }
        $view = $kind === ExternalRelationKind::View;
        Check::input(!$materializedView || $view, 'Only a view uses the native materialized view heading.');
        foreach ([$schema, $table, ...($view ? [] : $names)] as $object) {
            Check::input(
                preg_match('/\A.{1,64}(?<! )\z/us', $object->value) === 1,
                'A schema object name has 1 to 64 characters and no trailing space.',
            );
        }
        $seen = [];
        $native = new NativeLocalNames();
        foreach ($names as $name) {
            Check::input(!$view || strlen($name->value) <= 256, 'An external view field needs at most 256 native bytes.');
            $key = $view ? $native->key($name->value) : $name->key();
            Check::input(!isset($seen[$key]), 'A table source cannot repeat a column name.');
            $seen[$key] = true;
        }
        $types = $columnTypes === [] ? array_fill(0, count($names), ColumnKind::SignedInt) : $columnTypes;
        Check::input(array_is_list($types) && count($types) === count($names), 'A table source needs one native type per input slot.');
        $owned = [];
        foreach ($types as $type) {
            Check::input($type instanceof ColumnKind || $type instanceof IntegralSlot || $type instanceof Varchar, 'A table source needs checked native slot types.');
            $owned[] = $type;
        }
        $this->columns = $names;
        $this->columnTypes = $owned;
    }
}
