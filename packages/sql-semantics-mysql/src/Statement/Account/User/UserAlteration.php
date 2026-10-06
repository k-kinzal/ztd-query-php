<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Statement\Node;

/**
 * One account entry of ALTER USER: a specification of the account, or a change of its authentication factors.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html.
 *
 * @visibility public
 * @example Telling an account specification apart
 *     $entry = new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser());
 *     $entry instanceof \SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration // => true
 */
interface UserAlteration extends Node
{
}
