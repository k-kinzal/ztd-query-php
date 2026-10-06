<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

/**
 * The super-aggregate modifier of a GROUP BY clause.
 *
 * `WITH ROLLUP` follows the grouping list; `WITH CUBE` is accepted by the
 * 5.x grammars only (the server rejects it as not supported); `ROLLUP (...)`
 * and `CUBE (...)` enclose the grouping list (MySQL 8.3 and later grammars).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
 *
 * @visibility public
 * @example Reading the modifier of a grouping
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t GROUP BY a WITH ROLLUP');
 *     $query->statement->groupBy->modifier // => \SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier::WithRollup
 */
enum GroupingModifier
{
    case WithRollup;
    case WithCube;
    case Rollup;
    case Cube;
}
