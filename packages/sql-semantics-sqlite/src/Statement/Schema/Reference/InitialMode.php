<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

/**
 * When a deferrable foreign key is checked at first: the word written after INITIALLY.
 *
 * Source: https://sqlite.org/foreignkeys.html#fk_deferred.
 *
 * @visibility public
 * @example Reading the initial mode of a foreign key
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent DEFERRABLE INITIALLY DEFERRED)');
 *     $create->statement->columns[0]->constraints[1]->initially // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\InitialMode::Deferred
 */
enum InitialMode: string
{
    case Deferred = 'DEFERRED';
    case Immediate = 'IMMEDIATE';
}
