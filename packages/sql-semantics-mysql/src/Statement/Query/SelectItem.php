<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Statement\Node;

/**
 * One item of a select list: an expression with its optional alias, a star, or a qualified star.
 *
 * The query family provides the structures; DO and CREATE TABLE ... SELECT hold
 * them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 */
interface SelectItem extends Node
{
}
