<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A schema-qualified table or view and the named column slots it supplies.
 *
 * The slots identify inputs. Evaluation supplies the corresponding table
 * or view and columns, with their SQLite row values.
 *
 * @visibility public
 * @example Identifying a column of an external table
 *     $source = new \SqlSemantics\Platform\Sqlite\Ir\TableSource(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('accounts'), [new \SqlSemantics\Platform\Sqlite\Ir\Identifier('id')]);
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
     * Establishes a fixed external relation identity and an ordered list of unique column slots.
     *
     * @param list<Identifier> $columns The names at those slots
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $columns,
        public readonly ExternalRelationKind $kind = ExternalRelationKind::BaseTable,
    ) {
        $names = Check::listOf($columns, Identifier::class, 'A table source needs an ordered list of identifier slots.');
        $seen = [];
        foreach ($names as $name) {
            $key = $name->key();
            Check::input(!isset($seen[$key]), 'A table source cannot repeat a column name.');
            $seen[$key] = true;
        }
        $this->columns = $names;
    }
}
