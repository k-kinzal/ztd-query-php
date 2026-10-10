<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Catalog;

/**
 * Where a system variable has a value: for the server, for each session, or both.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html.
 *
 * @visibility public
 * @example Reading the reach of a variable with a session value
 *     \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach::Both->session() // => true
 */
enum Reach
{
    case Global;
    case Session;
    case Both;

    /**
     * Answers whether the variable has a session value.
     */
    public function session(): bool
    {
        return $this !== self::Global;
    }

    /**
     * Answers whether the variable has a global value.
     */
    public function global(): bool
    {
        return $this !== self::Session;
    }
}
