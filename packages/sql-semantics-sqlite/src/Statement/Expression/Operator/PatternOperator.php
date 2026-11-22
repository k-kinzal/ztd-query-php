<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

/**
 * The pattern matching operators; each one calls the function of the same name.
 *
 * Source: https://sqlite.org/lang_expr.html#the_like_glob_regexp_match_and_extract_operators.
 *
 * @visibility SqlSemantics
 */
enum PatternOperator: string
{
    case Like = 'LIKE';
    case Glob = 'GLOB';
    case Regexp = 'REGEXP';
    case Match = 'MATCH';
}
