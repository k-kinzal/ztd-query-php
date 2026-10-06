<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Statement\Node;

/**
 * One option written after the key parts of an index: KEY_BLOCK_SIZE, USING, COMMENT, WITH PARSER, visibility or an engine attribute.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 */
interface IndexOption extends Node
{
}
