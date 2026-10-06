<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

use SqlSemantics\Statement\Node;

/**
 * One item of the privilege or role list of GRANT and REVOKE.
 *
 * The server parses `GRANT a, b …` items as PT_role_or_privilege nodes and
 * reads them as roles when no ON clause follows and as privileges when one
 * does; the model holds each item as what the statement reads it as.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html.
 *
 * @visibility public
 * @example Telling a static privilege apart
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select) instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable // => true
 */
interface Grantable extends Node
{
}
