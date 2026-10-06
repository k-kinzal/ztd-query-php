<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * One change of ALTER TYPE to the attributes of a composite type: ADD, DROP or ALTER ATTRIBUTE.
 *
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility public
 * @example Telling that dropping an attribute is an attribute change
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\DropAttribute(new \SqlSemantics\Statement\Identifier\Name('a'))) instanceof \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AttributeChange // => true
 */
interface AttributeChange extends Clause
{
}
