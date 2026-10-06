<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Statement\Node;

/**
 * One table option, such as ENGINE, AUTO_INCREMENT or COMMENT.
 *
 * The table definition family provides the structures; ALTER TABLE holds them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 */
interface TableOption extends Node
{
}
