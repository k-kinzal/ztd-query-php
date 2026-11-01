<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

/**
 * Why a nested query cannot use a select-list name.
 *
 * @visibility public
 * @example Reading a forward-reference reason
 *     \SqlSemantics\Platform\MySql\Statement\Name\AliasRule::Forward->value // => 'forward reference in item list'
 */
enum AliasRule: string
{
    case Forward = 'forward reference in item list';
    case Aggregate = 'reference to group function';
    case Ambiguous = 'ambiguous select item';
}
