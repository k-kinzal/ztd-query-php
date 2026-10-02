<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Statement\Node;

/**
 * A window specification: an optional existing window name, partitioning, ordering and frame.
 *
 * It is written after OVER and in the WINDOW clause. The function-like expression
 * family provides the structure; the query family holds it in named windows.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html.
 */
interface WindowSpecification extends Node
{
}
