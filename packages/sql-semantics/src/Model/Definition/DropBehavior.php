<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * DropBehavior alternatives.
 *
 * @visibility public
 * @example Reading a drop behavior
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DROP TRIGGER tr ON t CASCADE', strict: false);
 *     $statement->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
enum DropBehavior: string
{
    case Default = '';
    case Restrict = 'RESTRICT';
    case Cascade = 'CASCADE';
}
