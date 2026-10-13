<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

/**
 * A type accepted in a column, routine parameter, variable or return declaration.
 *
 * CAST targets have their own type grammar and do not inhabit this interface.
 *
 * @visibility public
 * @example Distinguishing declaration types from conversion targets
 *     (new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)) instanceof \SqlSemantics\Platform\MySql\Statement\Type\DeclaredName // => true
 */
interface DeclaredName extends TypeName
{
}
