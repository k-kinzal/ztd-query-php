<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Statement\Node;

/**
 * One column of the COLUMNS clause of JSON_TABLE: an ordinality, path, EXISTS or NESTED column.
 *
 * The function-like expression family provides the structure; the query family
 * holds the columns in the JSON_TABLE relation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 */
interface JsonTableColumn extends Node
{
}
