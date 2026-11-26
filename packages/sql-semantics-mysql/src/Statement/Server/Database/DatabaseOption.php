<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Platform\MySql\Model\Statement\Node;

/**
 * An option of CREATE DATABASE or ALTER DATABASE.
 *
 * Mirrors the HA_CREATE_USED_* fields of HA_CREATE_INFO for a database.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-database.html.
 *
 * @visibility SqlSemantics
 */
interface DatabaseOption extends Node
{
}
