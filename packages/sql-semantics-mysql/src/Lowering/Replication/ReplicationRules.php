<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the replication family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-REPLICATION-ENTRY-001. Scope: CHANGE MASTER and REPLICATION SOURCE, START and STOP, group
 * replication, RESET, PURGE and BINLOG.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-replication-statements.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReplicationRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a replication statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::rule('MySQL replication family: statement');
    }

    /**
     * Lowers a FOR CHANNEL clause: a node of `opt_channel`; an absent clause is null.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function channel(Node $channel): ?Text
    {
        throw ImplementationGap::rule('MySQL replication family: channel');
    }
}
