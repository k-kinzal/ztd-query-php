<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View;

/**
 * The security context of a view: SQL SECURITY DEFINER or INVOKER.
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-objects-security.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\View\ViewSecurity::Invoker->value // => 'INVOKER'
 */
enum ViewSecurity: string
{
    case Definer = 'DEFINER';
    case Invoker = 'INVOKER';
}
