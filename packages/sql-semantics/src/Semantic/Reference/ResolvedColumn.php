<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Reference;

use SqlSemantics\Semantic\Relation\TableReference;
use SqlSemantics\Semantic\Schema\Column;

/**
 * A verified declaration and the particular relation through which it is read.
 * @example Reading semantic relationships
 *     $dialect = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite;
 *     $column = new \SqlSemantics\Semantic\Schema\Column(new \SqlSemantics\Semantic\Name('foo'), new \SqlSemantics\Core\Type\TypeDescriptor($dialect, 'integer'));
 *     $table = new \SqlSemantics\Semantic\Schema\Table($dialect, new \SqlSemantics\Semantic\QualifiedName(new \SqlSemantics\Semantic\Name('bar')), $column);
 *     $statement = (new \SqlSemantics\Facade\Semantics($dialect))->analyze('SELECT foo FROM bar', [$table]);
 *     $statement->field('foo')->expression->binding->column === $column // => true
 *
 * @visibility public
 */
final class ResolvedColumn
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly TableReference $relation, public readonly Column $column)
    {
        assert($relation->declaration !== null && in_array($column, $relation->declaration->columns, true), 'The column must belong to this relation declaration.');
    }
}
