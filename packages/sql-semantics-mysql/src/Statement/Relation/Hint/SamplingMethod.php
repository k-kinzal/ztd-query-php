<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Hint;

/**
 * The sampling method of a TABLESAMPLE clause (MySQL 9.x grammars).
 *
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/9.1/en/select.html.
 *
 * @visibility public
 * @example Reading the keyword of a sampling method
 *     \SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod::Bernoulli->value // => 'BERNOULLI'
 */
enum SamplingMethod: string
{
    case System = 'SYSTEM';
    case Bernoulli = 'BERNOULLI';
}
