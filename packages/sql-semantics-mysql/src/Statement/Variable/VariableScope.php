<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

/**
 * The scope a system variable is read from or assigned in.
 *
 * `LOCAL` is a synonym of `SESSION`. `PERSIST` and `PERSIST_ONLY` are scopes
 * of SET only.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 *
 * @visibility public
 * @example Reading the keyword of a scope
 *     \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::PersistOnly->value // => 'PERSIST_ONLY'
 */
enum VariableScope: string
{
    case Global = 'GLOBAL';
    case Session = 'SESSION';
    case Persist = 'PERSIST';
    case PersistOnly = 'PERSIST_ONLY';
}
