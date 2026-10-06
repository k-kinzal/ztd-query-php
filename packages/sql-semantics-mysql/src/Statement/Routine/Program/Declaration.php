<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Statement\Node;

/**
 * A DECLARE statement at the start of a BEGIN ... END block: local variables, a condition, a cursor or a handler.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare.html.
 *
 * @visibility public
 * @example Reading the declarations of a block
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE a INT; END');
 *     $create->statement->body->declarations[0] instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Declaration // => true
 */
interface Declaration extends Node
{
}
