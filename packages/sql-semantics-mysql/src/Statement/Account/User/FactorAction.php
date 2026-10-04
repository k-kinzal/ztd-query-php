<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

/**
 * What ALTER USER does to an authentication factor: ADD, MODIFY or DROP.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-multifactor.
 *
 * @visibility public
 * @example Reading the keyword of an action
 *     \SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction::Modify->value // => 'MODIFY'
 */
enum FactorAction: string
{
    case Add = 'ADD';
    case Modify = 'MODIFY';
    case Drop = 'DROP';
}
