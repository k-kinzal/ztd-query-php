<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\ModifierRule;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\StandaloneRule;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\PartitionRule;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;
use SqlSemantics\Platform\MySql\Statement\Statement;

/**
 * The entry rules of the table change family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-TABLE-CHANGE-ENTRY-001. Scope: ALTER TABLE, partitioning, DROP TABLE and INDEX, RENAME TABLE and
 * TRUNCATE. The method names, parameters and return types are fixed by the
 * family plan; each delegates to the rule classes of this family
 * (MYSQL-TABLE-CHANGE-STATEMENT-001, MYSQL-ALTER-STATEMENT-001,
 * MYSQL-PARTITION-CLAUSE-001, MYSQL-ALTER-MODIFIER-001,
 * MYSQL-ALTER-STANDALONE-001). Terminates: delegates once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableChangeRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Retains the legacy IGNORE notice after the alteration target is complete.
     *
     * @param list<\SqlParser\Lexer\Token> $tokens Transient tokens used only to locate the target's command role
     */
    public function ignored(Node $node, array $tokens): ?\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated
    {
        if ($node->name !== 'table_ident' || $this->lowering->profile->grammar !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return null;
        }
        $start = $node->tokens()[0]->offset ?? -1;
        foreach ($tokens as $index => $token) {
            if ($token->offset === $start && $index >= 3 && array_column(array_slice($tokens, $index - 3, 3), 'name') === ['ALTER', 'IGNORE_SYM', 'TABLE_SYM']) {
                return \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::AlterIgnore;
            }
        }
        return null;
    }

    /**
     * Returns an aborting notice for a completed option before publishing a statement.
     *
     * @param bool $partitionNames Whether a partial parse needs partition-name notices; complete definitions check their own names
     */
    public function completed(Node $node, bool $partitionNames = true): ?\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure
    {
        $notice = (new PartitionRule($this->lowering))->completed($node) ?? ($partitionNames ? (new Partition\DefinitionRule($this->lowering))->completed($node) : null) ?? (new ModifierRule($this->lowering))->completed($node);
        if ($notice !== null || $node->name !== 'alter_list_item' || !in_array($this->lowering->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            return $notice;
        }
        $rule = new Alter\ColumnItemRule($this->lowering);
        $form = $this->lowering->form($node);
        try {
            if ($rule->claims($form->signature)) {
                $rule->item($form);
            }
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($failure->problem, $failure->aborts);
        } catch (\SqlSemantics\Diagnostic\AnalysisException) {
            // Other refusals retain their ordinary input formatting.
        }
        return null;
    }

    /**
     * Lowers a table change statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When column operands contradict their type
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a declared operand is invalid
     */
    public function statement(Node $statement): Statement
    {
        return (new StatementRule($this->lowering))->statement($this->lowering->form($statement));
    }

    /**
     * Lowers a production of `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this family.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When column operands contradict their type
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a declared operand is invalid
     */
    public function definition(Form $form): Statement
    {
        return (new StatementRule($this->lowering))->statement($form);
    }

    /**
     * Lowers the partitioning of CREATE TABLE: a node of `opt_create_partitioning` or `partition_clause`;
     * an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function partitioning(Node $clause): ?Partitioning
    {
        return (new PartitionRule($this->lowering))->partitioning($clause);
    }

    /**
     * Lowers the ALGORITHM and LOCK options of CREATE INDEX and DROP INDEX: a node of
     * `opt_index_lock_algorithm` or `opt_index_lock_and_algorithm`.
     *
     * @return list<AlterOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function alterOptions(Node $options): array
    {
        return (new ModifierRule($this->lowering))->pair($options);
    }

    /**
     * Lowers the partitions a maintenance operation names: a node of `all_or_alt_part_name_list`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function partitionNames(Node $list): PartitionSelection
    {
        return (new StandaloneRule($this->lowering))->selection($list);
    }
}
