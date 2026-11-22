<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Hint;

/**
 * What an index hint asks the optimizer: USE, FORCE or IGNORE the listed indexes.
 *
 * Each case holds its keyword. The KEY and INDEX keywords after it are
 * synonyms; the writer emits INDEX.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/index-hints.html.
 *
 * @visibility SqlSemantics
 */
enum IndexHintAction: string
{
    case Use = 'USE';
    case Force = 'FORCE';
    case Ignore = 'IGNORE';
}
