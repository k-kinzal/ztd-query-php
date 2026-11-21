<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A schema-qualified external source and the named column slots it supplies.
 *
 * The slots identify inputs. Evaluation supplies the corresponding table
 * and columns, with their PostgreSQL row values.
 *
 * @visibility public
 * @example Identifying a column of an external table
 *     $source = new \SqlSemantics\Platform\PostgreSql\Ir\TableSource(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('main'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('accounts'), [new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('id')]);
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
     * Establishes a fixed external table identity and an ordered list of unique column slots.
     *
     * @param list<Identifier> $columns The names at those slots
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $columns,
        public readonly bool $only = false,
    ) {
        $names = Check::listOf($columns, Identifier::class, 'A table source needs an ordered list of identifier slots.');
        $seen = [];
        foreach ($names as $name) {
            $key = $name->value;
            Check::input(!isset($seen[$key]), 'A table source cannot repeat a column name.');
            $seen[$key] = true;
        }
        $this->columns = $names;
    }
}
