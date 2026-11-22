<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

/**
 * What happens to the child rows when their parent key is deleted or changed.
 *
 * Source: https://sqlite.org/foreignkeys.html#fk_actions.
 *
 * @visibility SqlSemantics
 */
enum ReferenceReaction: string
{
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
    case Cascade = 'CASCADE';
    case Restrict = 'RESTRICT';
    case NoAction = 'NO ACTION';
}
