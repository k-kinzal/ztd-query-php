<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

/**
 * The interchangeable commands that request an explanation or describe a table.
 *
 * Keeping this finite choice preserves the request without storing SQL fragments.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html.
 *
 * @visibility public
 * @example Reading the abbreviated command
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainVerb::Desc->value // => 'DESC'
 */
enum ExplainVerb: string
{
    case Explain = 'EXPLAIN';
    case Describe = 'DESCRIBE';
    case Desc = 'DESC';
}
