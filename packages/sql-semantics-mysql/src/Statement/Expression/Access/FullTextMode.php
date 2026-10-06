<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

/**
 * The search mode of MATCH … AGAINST.
 *
 * The natural language mode is the default; `IN NATURAL LANGUAGE MODE`
 * names it explicitly and is not written. Query expansion is a natural
 * language search followed by a second search.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html.
 *
 * @visibility public
 * @example Reading the boolean mode
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE MATCH (a) AGAINST ('x' IN BOOLEAN MODE)");
 *     $query->statement->where->mode // => \SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode::Boolean
 */
enum FullTextMode
{
    case NaturalLanguage;
    case QueryExpansion;
    case Boolean;
}
