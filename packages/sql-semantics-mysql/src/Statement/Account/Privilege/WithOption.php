<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Statement\Node;

/**
 * One option of the WITH clause of GRANT: GRANT OPTION, or in MySQL 5.x a resource limit.
 *
 * Source: https://dev.mysql.com/doc/refman/5.7/en/grant.html.
 *
 * @visibility public
 * @example Telling the grant option apart
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight() instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption // => true
 */
interface WithOption extends Node
{
}
