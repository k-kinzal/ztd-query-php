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
 * @visibility public
 * @example Reading the action of an index hint
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t FORCE INDEX (i)');
 *     $query->statement->from->indexHints[0]->action // => \SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction::Force
 */
enum IndexHintAction: string
{
    case Use = 'USE';
    case Force = 'FORCE';
    case Ignore = 'IGNORE';
}
