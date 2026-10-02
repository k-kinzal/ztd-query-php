<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Statement\Node;

/**
 * An account at a position that names a user or a role: a written account name or the current user.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-names.html.
 *
 * @visibility public
 * @example Telling the two kinds of account apart
 *     $account = new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser();
 *     $account instanceof \SqlSemantics\Platform\MySql\Statement\Name\Account // => true
 */
interface Account extends Node
{
}
