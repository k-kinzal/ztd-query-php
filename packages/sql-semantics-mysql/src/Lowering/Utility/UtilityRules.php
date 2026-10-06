<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Utility\Explain\ExplainRule;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\SetRule;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\LegacyShowRule;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ShowRule;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the utility family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-UTILITY-ENTRY-001. Scope: SET, SHOW, EXPLAIN, DESCRIBE, HELP and USE.
 * The method names, parameters and return types are fixed by the family
 * plan. A statement node is handed to the rule class of its statement rule:
 * SET (MYSQL-SET-LOWERING-001), the SHOW rules of MySQL 5.x
 * (MYSQL-SHOW-LEGACY-LOWERING-001) and 8.0 and later
 * (MYSQL-SHOW-LOWERING-001), and EXPLAIN, DESCRIBE, HELP and USE
 * (MYSQL-EXPLAIN-LOWERING-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-utility-statements.html.
 * Status: Implemented.
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
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->form($statement);

        return match ($statement->name) {
            'set' => (new SetRule($this->lowering))->statement($form),
            'show' => (new LegacyShowRule($this->lowering))->statement($form),
            'describe', 'describe_stmt', 'explain_stmt', 'help', 'use' => (new ExplainRule($this->lowering))->statement($form),
            default => (new ShowRule($this->lowering))->statement($form),
        };
    }

    /**
     * Confirms that a node is `master_or_binary`, whose MASTER and BINARY are the same keyword before LOGS.
     *
     * The writer emits BINARY, which every release accepts (UtilityNoise).
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function binaryLogsWord(Node $word): void
    {
        $form = $this->lowering->form($word);
        if ($form->signature !== 'master_or_binary: MASTER_SYM' && $form->signature !== 'master_or_binary: BINARY' && $form->signature !== 'master_or_binary: BINARY_SYM') {
            throw ImplementationGap::production($form);
        }
    }
}
