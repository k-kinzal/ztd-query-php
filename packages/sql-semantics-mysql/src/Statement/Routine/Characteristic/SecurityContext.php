<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

/**
 * Whose privileges a stored routine runs with: those of its DEFINER or of its INVOKER.
 *
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-objects-security.html.
 *
 * @visibility public
 * @example Reading the keyword of a context
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SecurityContext::Invoker->value // => 'INVOKER'
 */
enum SecurityContext: string
{
    case Definer = 'DEFINER';
    case Invoker = 'INVOKER';
}
