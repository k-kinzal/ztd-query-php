<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Into;

use SqlSemantics\Statement\Node;

/**
 * Where an INTO clause sends the rows of a query: variables, an outfile or a dumpfile.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html.
 */
interface IntoDestination extends Node
{
}
