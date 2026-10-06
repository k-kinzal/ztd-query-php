<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * A MySQL data type as a statement writes it: the declared type of a column, parameter or variable, or a cast target.
 *
 * The value is the type that was requested, with its length, precision and
 * attributes exactly as written. It is also the type descriptor of
 * declarations and facts. What the server stores for a request that depends
 * on session settings, such as REAL under REAL_AS_FLOAT, is not decided here.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-types.html.
 *
 * @visibility public
 * @example Naming a type by its base keywords
 *     (new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int, '11'))->name() // => 'INT'
 */
interface TypeName extends Node, TypeDescriptor
{
}
