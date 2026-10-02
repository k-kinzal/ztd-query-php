<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\CheckOption;
use SqlSemantics\Platform\MySql\Statement\Server\RepairOption;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the server family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-SERVER-ENTRY-001. Scope: transactions, locks, table maintenance, FLUSH, KILL, plugins,
 * databases, servers, tablespaces and log file groups.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-server-administration-statements.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ServerRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a server administration statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::rule('MySQL server family: statement');
    }

    /**
     * Lowers a production of `create`, `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this
     * family.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function definition(Form $form): Statement
    {
        throw ImplementationGap::rule('MySQL server family: definition');
    }

    /**
     * Lowers the options of a table check: a node of `opt_mi_check_type` or `opt_mi_check_types`; no
     * option is empty.
     *
     * @return list<CheckOption>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function checkOptions(Node $options): array
    {
        throw ImplementationGap::rule('MySQL server family: checkOptions');
    }

    /**
     * Lowers the options of a table repair: a node of `opt_mi_repair_type` or `opt_mi_repair_types`; no
     * option is empty.
     *
     * @return list<RepairOption>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function repairOptions(Node $options): array
    {
        throw ImplementationGap::rule('MySQL server family: repairOptions');
    }
}
