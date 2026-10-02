<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Statement\Node;

/**
 * A WITH clause: common table expressions and the RECURSIVE marker.
 *
 * The query family provides the structure; UPDATE and DELETE statements hold it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/with.html.
 */
interface WithClause extends Node
{
}
