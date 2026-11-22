<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

use SqlSemantics\Platform\MySql\Model\Statement\Node;

/**
 * One characteristic of a stored routine: COMMENT, LANGUAGE, the data access, SQL SECURITY or DETERMINISTIC.
 *
 * A statement keeps its characteristics in written order; when one kind is
 * written several times the server uses the last.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility SqlSemantics
 */
interface Characteristic extends Node
{
}
