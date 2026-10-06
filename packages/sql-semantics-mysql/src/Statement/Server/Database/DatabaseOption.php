<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Statement\Node;

/**
 * An option of CREATE DATABASE or ALTER DATABASE.
 *
 * Mirrors the HA_CREATE_USED_* fields of HA_CREATE_INFO for a database.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-database.html.
 *
 * @visibility public
 * @example Reading the options of CREATE DATABASE
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE DATABASE d CHARACTER SET utf8mb4');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption // => true
 */
interface DatabaseOption extends Node
{
}
