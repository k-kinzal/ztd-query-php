<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

/**
 * What happens to the child rows when their parent key is deleted or changed.
 *
 * Source: https://sqlite.org/foreignkeys.html#fk_actions.
 *
 * @visibility public
 * @example Reading the reaction of a foreign key action
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent ON UPDATE SET NULL)');
 *     $create->statement->columns[0]->constraints[0]->arguments[0]->reaction // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceReaction::SetNull
 */
enum ReferenceReaction: string
{
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
    case Cascade = 'CASCADE';
    case Restrict = 'RESTRICT';
    case NoAction = 'NO ACTION';
}
