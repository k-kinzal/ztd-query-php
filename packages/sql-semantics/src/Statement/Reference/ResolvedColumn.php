<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference;

use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\Table;

/**
 * Exact declaration identity and the occurrence through which a column is read.
 * @visibility public
 * @example Reading the owning occurrence
 *     $column = new \SqlSemantics\Statement\Schema\Column(new \SqlSemantics\Statement\Identifier\Name('id'), new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::Integer));
 *     $table = new \SqlSemantics\Statement\Schema\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')),new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $column);
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), \SqlSemantics\Statement\Identifier\Comparison::Sensitive, \SqlSemantics\Statement\Identifier\Comparison::Sensitive, true, null, null,new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table);
 *     $relation = new \SqlSemantics\Statement\Relation\TableReference($catalog, $table->name);
 *     (new \SqlSemantics\Statement\Reference\ResolvedColumn($relation, $table, $column))->column === $column // => true
 */
final class ResolvedColumn
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Asserts identity membership, not merely equal names or equal column facts.
     */
    public function __construct(public readonly TableReference $relation, public readonly Table $table, public readonly Column $column)
    {
        \SqlSemantics\Statement\Validation\Check::input(in_array($table, $relation->declarations, true), 'The table must be a declaration of this relation occurrence.');
        \SqlSemantics\Statement\Validation\Check::input($table->ownsColumn($column), 'The column must belong to the referenced table declaration.');
    }
}
