<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the utility family: transactions, configuration, EXPLAIN, maintenance, locking, notification, CALL and DO.
 *
 * Rule: PG-UTILITY-001. Scope: the statement nonterminals of the family
 * (`php .agent/pg-families.php Utility`), each handed to the rule of its
 * group, and `SetResetClause`, `FunctionSetResetClause`. Termination: each
 * statement is lowered by one rule; lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class UtilityCommands
{
    /**
     * The rule group of each statement nonterminal.
     */
    private const GROUPS = [
        'TransactionStmt' => 'transaction', 'TransactionStmtLegacy' => 'transaction',
        'VariableSetStmt' => 'setting', 'VariableResetStmt' => 'setting', 'VariableShowStmt' => 'setting', 'AlterSystemStmt' => 'setting',
        'ConstraintsSetStmt' => 'setting', 'DiscardStmt' => 'setting',
        'VacuumStmt' => 'maintenance', 'AnalyzeStmt' => 'maintenance', 'ClusterStmt' => 'maintenance', 'ReindexStmt' => 'maintenance',
        'CheckPointStmt' => 'maintenance', 'ExplainStmt' => 'maintenance', 'LockStmt' => 'maintenance',
        'CallStmt' => 'command', 'DoStmt' => 'command', 'NotifyStmt' => 'command', 'ListenStmt' => 'command', 'UnlistenStmt' => 'command', 'LoadStmt' => 'command',
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family, such as `TransactionStmt`, `TransactionStmtLegacy`, `VariableSetStmt`, `ExplainStmt` or `VacuumStmt`.
     *
     * @throws ImplementationGap When the nonterminal is not a statement of the family
     */
    public function statement(Node $statement): Statement
    {
        $group = self::GROUPS[$statement->name] ?? throw ImplementationGap::production($this->lowering->productions->form($statement));

        return match ($group) {
            'transaction' => (new TransactionRule($this->lowering))->statement($statement),
            'setting' => (new SettingRule($this->lowering))->statement($statement),
            'maintenance' => (new MaintenanceRule($this->lowering))->statement($statement),
            'command' => (new CommandRule($this->lowering))->statement($statement),
        };
    }

    /**
     * Lowers `SetResetClause` or `FunctionSetResetClause`: a SET or RESET of a configuration parameter attached to a role, a database or a routine.
     *
     * The result is the same structure the SET or RESET command has on its
     * own; it renders as that command, which is how the clause is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function setReset(Node $clause): Statement
    {
        return (new SettingRule($this->lowering))->clause($clause);
    }
}
