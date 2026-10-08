<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint;

/**
 * One optimizer hint of a hint comment, the comment that starts with `/*+`.
 *
 * A hint comment follows the keyword that starts a query block or a
 * statement: SELECT, INSERT, REPLACE, UPDATE or DELETE. Its hints are kept
 * in written order with the block or the statement; the server reads them
 * when it resolves the statement. A hint that is not well formed ends the
 * hints the comment holds, so it is not one of them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 *
 * @visibility public
 * @example Reading the hints of a query block
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT /*+ BKA(t) MAX_EXECUTION_TIME(5) *' . '/ 1');
 *     array_map(static fn ($hint) => $hint->name(), $query->statement->hints) // => [\SqlSemantics\Platform\MySql\Statement\Hint\HintName::Bka, \SqlSemantics\Platform\MySql\Statement\Hint\HintName::MaxExecutionTime]
 */
interface OptimizerHint
{
    /**
     * Answers the name of the hint.
     */
    public function name(): HintName;

    /**
     * Answers the hint as it is written in a hint comment, with every name quoted.
     */
    public function text(): string;
}
