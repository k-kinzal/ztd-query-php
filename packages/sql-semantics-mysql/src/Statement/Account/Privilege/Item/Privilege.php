<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

/**
 * A privilege operand of GRANT or REVOKE with an ON clause.
 *
 * Roles are separate account operands and cannot inhabit this domain.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html.
 *
 * @visibility public
 * @example Recognizing a privilege operand
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges() instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege // => true
 */
interface Privilege extends Grantable
{
}
