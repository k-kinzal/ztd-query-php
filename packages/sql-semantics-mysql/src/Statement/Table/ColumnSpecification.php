<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Statement\Node;

/**
 * The definition of a column without its name: data type, attributes, generation expression and reference.
 *
 * The table definition family provides the structure; ALTER TABLE ... ADD, CHANGE
 * and MODIFY hold it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 */
interface ColumnSpecification extends Node
{
}
