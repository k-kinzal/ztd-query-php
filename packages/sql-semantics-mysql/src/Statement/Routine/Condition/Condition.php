<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Statement\Node;

/**
 * What a handler handles or a signal raises: an error code, an SQLSTATE value, a condition name or a class of conditions.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 *
 * @visibility public
 * @example Telling the kinds of condition apart
 *     $state = new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('45000'));
 *     $state instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition // => true
 */
interface Condition extends Node
{
}
