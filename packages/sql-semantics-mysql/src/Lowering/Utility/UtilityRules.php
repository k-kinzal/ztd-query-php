<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the utility family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-UTILITY-ENTRY-001. Scope: SET, SHOW, EXPLAIN, DESCRIBE, HELP and USE.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-utility-statements.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class UtilityRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a utility statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::rule('MySQL utility family: statement');
    }
}
