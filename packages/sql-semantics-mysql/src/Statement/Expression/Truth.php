<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

/**
 * The truth values a truth test compares with: TRUE, FALSE and UNKNOWN.
 *
 * UNKNOWN is the truth value of NULL. Each case holds the keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#operator_is.
 *
 * @visibility SqlSemantics
 */
enum Truth: string
{
    case True = 'TRUE';
    case False = 'FALSE';
    case Unknown = 'UNKNOWN';
}
