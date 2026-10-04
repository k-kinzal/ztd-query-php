<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\MaintenanceRule;
use SqlSemantics\Platform\MySql\Statement\Server\CheckOption;
use SqlSemantics\Platform\MySql\Statement\Server\RepairOption;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the server family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-SERVER-ENTRY-001. Scope: transactions, XA, locks, table
 * maintenance, key caches, FLUSH, KILL, plugins and components, databases,
 * foreign servers, spatial reference systems, tablespaces, undo tablespaces,
 * log file groups, ALTER INSTANCE, CLONE, SHUTDOWN and RESTART. The method
 * names, parameters and return types are fixed by the family plan; each
 * delegates to the rule class of its area (MYSQL-SERVER-STATEMENT-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-server-administration-statements.html.
 * Status: Implemented.
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
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the server rejects the statement while it parses it
     */
    public function statement(Node $statement): Statement
    {
        return (new StatementRule($this->lowering))->statement($statement);
    }

    /**
     * Lowers a production of `create`, `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this
     * family.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the server rejects the statement while it parses it
     */
    public function definition(Form $form): Statement
    {
        return (new StatementRule($this->lowering))->definition($form);
    }

    /**
     * Lowers the options of a table check: a node of `opt_mi_check_type` or `opt_mi_check_types`; no
     * option is empty.
     *
     * @return list<CheckOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function checkOptions(Node $options): array
    {
        return (new MaintenanceRule($this->lowering))->checkOptions($options);
    }

    /**
     * Lowers the options of a table repair: a node of `opt_mi_repair_type` or `opt_mi_repair_types`; no
     * option is empty.
     *
     * @return list<RepairOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function repairOptions(Node $options): array
    {
        return (new MaintenanceRule($this->lowering))->repairOptions($options);
    }
}
