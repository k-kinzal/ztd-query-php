<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Statement\Node;

/**
 * One element of a table definition: a column, an index, or a constraint.
 *
 * The table definition family provides the structures; ALTER TABLE ... ADD holds
 * them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 */
interface TableElement extends Node
{
}
