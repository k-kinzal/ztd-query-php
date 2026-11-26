<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

/**
 * When a deferrable foreign key is checked at first: the word written after INITIALLY.
 *
 * Source: https://sqlite.org/foreignkeys.html#fk_deferred.
 *
 * @visibility SqlSemantics
 */
enum InitialMode: string
{
    case Deferred = 'DEFERRED';
    case Immediate = 'IMMEDIATE';
}
