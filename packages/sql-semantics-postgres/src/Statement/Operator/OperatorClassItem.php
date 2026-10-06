<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * One item of an operator class or of an operator family addition: an operator, a support function or the storage type.
 *
 * Mirrors PostgreSQL's `CreateOpClassItem` (`itemtype` OPERATOR, FUNCTION or STORAGE).
 * Source: https://www.postgresql.org/docs/17/sql-createopclass.html.
 *
 * @visibility public
 * @example Telling that a storage type is an item
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('int4')])));
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Operator\StorageMember($type) instanceof \SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorClassItem // => true
 */
interface OperatorClassItem extends Clause
{
}
