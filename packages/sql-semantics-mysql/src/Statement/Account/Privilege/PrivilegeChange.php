<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Statement\Node;

/**
 * A privilege instruction whose implicit commit excludes function and trigger bodies.
 *
 * The restriction concerns the enclosing declaration, including unreachable
 * instructions. Procedure bodies may retain these instructions.
 *
 * @visibility public
 * @example Identifying the instruction's declaration constraint
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT ALL ON *.* TO u') instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\PrivilegeChange // => true
 */
interface PrivilegeChange extends Node
{
}
