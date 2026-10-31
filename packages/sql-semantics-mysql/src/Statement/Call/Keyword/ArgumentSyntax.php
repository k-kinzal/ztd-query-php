<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Keyword;

/**
 * The separators of a keyword call; FROM and FOR name SUBSTRING operands.
 *
 * @visibility public
 * @example Selecting the named SUBSTRING operands
 *     \SqlSemantics\Platform\MySql\Statement\Call\Keyword\ArgumentSyntax::FromFor->value // => 'FROM FOR'
 */
enum ArgumentSyntax: string
{
    case Commas = ',';
    case FromFor = 'FROM FOR';
}
