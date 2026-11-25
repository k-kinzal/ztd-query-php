<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

/**
 * A declared native InnoDB action for one parent-row event.
 * @visibility public
 * @example Reading the action metadata
 *     \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction::SetNull->value // => 'SET NULL'
 */
enum ForeignKeyAction: string
{
    case Restrict = 'RESTRICT';
    case NoAction = 'NO ACTION';
    case Cascade = 'CASCADE';
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
}
