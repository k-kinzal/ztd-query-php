<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Platform\MySql\Model\Statement\Node;

/**
 * A DECLARE statement at the start of a BEGIN ... END block: local variables, a condition, a cursor or a handler.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare.html.
 *
 * @visibility SqlSemantics
 */
interface Declaration extends Node
{
}
