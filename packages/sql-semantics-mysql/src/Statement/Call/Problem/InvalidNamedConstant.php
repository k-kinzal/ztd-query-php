<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * NAME_CONST has a NULL name or an argument that is not an accepted literal form.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html#function_name-const.
 *
 * @visibility public
 * @example Describing an invalid literal argument
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant())->message() // => 'Incorrect arguments to NAME_CONST'
 */
final class InvalidNamedConstant implements Diagnostic
{
    use Snapshot;

    /**
     * @param bool $nullName Whether otherwise valid arguments use a NULL name
     */
    public function __construct(public readonly bool $nullName = false)
    {
    }

    /**
     * Describes the invalid argument form in the server's words.
     */
    public function message(): string
    {
        return $this->nullName ? "The 'NAME_CONST' syntax is reserved for purposes internal to the MySQL server" : 'Incorrect arguments to NAME_CONST';
    }
}
