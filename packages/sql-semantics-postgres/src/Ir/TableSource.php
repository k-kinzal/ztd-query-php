<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A schema-qualified external source and the named column slots it supplies.
 *
 * The slots identify inputs. Evaluation supplies the corresponding table
 * or view and its columns, with their PostgreSQL row values.
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
     * @var list<ColumnKind> The explicit native kinds, or an empty list for an int4 source
     */
    public readonly array $columnKinds;

    /**
     * Establishes a fixed external relation identity and an ordered list of unique column slots.
     *
     * @param list<Identifier> $columns The names at those slots
     * @param list<ColumnKind> $columnKinds The resolved kinds, defaulting to int4 for a signed source
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $columns,
        public readonly bool $only = false,
        array $columnKinds = [],
        public readonly ExternalRelationKind $kind = ExternalRelationKind::BaseTable,
    ) {
        $names = Check::listOf($columns, Identifier::class, 'A table source needs an ordered list of identifier slots.');
        $seen = [];
        foreach ($names as $name) {
            $key = $name->value;
            Check::input(!isset($seen[$key]), 'A table source cannot repeat a column name.');
            $seen[$key] = true;
        }
        $kinds = Check::listOf($columnKinds, ColumnKind::class, 'A table source needs an ordered list of native column kinds.');
        Check::input($kinds === [] || count($kinds) === count($names), 'Every table source field needs one native column kind.');
        $this->columns = $names;
        $this->columnKinds = $kinds;
    }

    /**
     * Answers the native kind of a visible position, including the int4 shorthand.
     */
    public function columnKind(int $slot): ?ColumnKind
    {
        return isset($this->columns[$slot]) ? ($this->columnKinds[$slot] ?? ColumnKind::Int4) : null;
    }
}
