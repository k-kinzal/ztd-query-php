<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Statement\Node;

/**
 * The partitioning of a table: PARTITION BY with its function, columns, counts and partition definitions.
 *
 * The table change family provides the structure; CREATE TABLE holds it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning.html.
 */
interface Partitioning extends Node
{
}
