<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Schema;

use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Name;

/**
 * A declared column, independent of SQL source syntax.
 * @example Reading semantic relationships
 *     $dialect = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite;
 *     $column = new \SqlSemantics\Semantic\Schema\Column(new \SqlSemantics\Semantic\Name('foo'), new \SqlSemantics\Core\Type\TypeDescriptor($dialect, 'integer'));
 *     $table = new \SqlSemantics\Semantic\Schema\Table($dialect, new \SqlSemantics\Semantic\QualifiedName(new \SqlSemantics\Semantic\Name('bar')), $column);
 *     $column->type->name // => 'integer'
 *
 * @visibility public
 */
final class Column
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(
        public readonly Name $name,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability = Nullability::MaybeNull,
    ) {
        assert($type->name !== 'unknown', 'A column declaration must supply its type.');
    }
}
