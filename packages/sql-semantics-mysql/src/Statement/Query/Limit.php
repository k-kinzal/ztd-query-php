<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Statement\Node;

/**
 * A LIMIT clause: a row count and an optional offset, in the form written.
 *
 * The query family provides the structure; UPDATE, DELETE, HANDLER and SHOW
 * statements hold it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 */
interface Limit extends Node
{
}
