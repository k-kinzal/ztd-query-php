<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\FactorRule as LegacyFactorRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\JoinRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\FactorRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\ReferenceRule;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;

/**
 * Lowers the FROM clause and the table reference lists of every grammar generation, handing each table factor to its generation.
 *
 * Rule: MYSQL-FROM-001. Scope: from_clause, opt_from_clause, from_tables,
 * table_reference_list, and the dispatch of table_factor. FROM DUAL is the
 * dummy table; references separated by commas are a comma list; one
 * reference is itself. Constructs: Dual, TableList. Terminates: lists are
 * flattened iteratively. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/join.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class FromRule
{
    /**
     * The table factor productions of the 8.0 and later grammars.
     */
    private const MODERN = [
        'table_factor: single_table' => true, 'table_factor: single_table_parens' => true, 'table_factor: derived_table' => true,
        'table_factor: joined_table_parens' => true, 'table_factor: table_reference_list_parens' => true, 'table_factor: table_function' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an optional FROM clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When an input relation cannot be lowered
     */
    public function from(Node $clause): Relation|Dual|null
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_from_clause:' => null,
            'opt_from_clause: from_clause' => $this->from($form->node(0)),
            'from_clause: FROM from_tables', 'from_clause: FROM table_reference_list' => $this->from($form->node(1)),
            'from_tables: DUAL_SYM', 'table_reference_list: DUAL_SYM' => new Dual(),
            default => $this->tables($clause),
        };
    }

    /**
     * Lowers the table references of SELECT, UPDATE or DELETE: one reference or a comma list.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When an input relation cannot be lowered
     */
    public function tables(Node $tables): Relation
    {
        $members = $this->members($tables);

        return count($members) === 1 ? $members[0] : new TableList($members);
    }

    /**
     * Lowers the references of a FROM clause in written order.
     *
     * @return list<Relation>
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When an input relation cannot be lowered
     */
    public function members(Node $tables): array
    {
        $form = $this->lowering->form($tables);
        if ($form->signature === 'from_tables: table_reference_list') {
            return $this->members($form->node(0));
        }
        if ($form->signature === 'table_reference_list: join_table_list' || $form->signature === 'join_table_list: derived_table_list') {
            return $this->checked((new JoinRule($this->lowering))->list($form->node(0)), $tables);
        }
        if ($form->signature !== 'table_reference_list: table_reference' && $form->signature !== 'table_reference_list: table_reference_list , table_reference') {
            throw ImplementationGap::production($form);
        }
        $members = [];
        $items = (new Lists())->items($tables);
        foreach ($items as $item) {
            $members[] = (new ReferenceRule($this->lowering))->reference($item);
        }

        return $this->checked($members, $tables, $items);
    }

    /**
     * Refuses a list whose relations repeat a name, as the server does while reading the clause.
     *
     * @param list<Relation> $members
     * @return list<Relation>
     *
     * @throws \SqlSemantics\Construction\RejectedInput When a relation name is written twice
     * @param list<Node> $items The table reference nodes the members were lowered from, in order
     */
    public function checked(array $members, Node $tables, array $items = []): array
    {
        $names = new \SqlSemantics\Platform\MySql\Rules\Query\From\RelationNames();
        $problem = $names->repeated($members);
        if ($problem === null) {
            return $members;
        }
        $at = $tables;
        foreach ($items as $index => $item) {
            if ($names->repeated(array_slice($members, 0, $index + 1)) !== null) {
                $at = $item;
                break;
            }
        }

        throw new \SqlSemantics\Construction\RejectedInput($problem, (new \SqlSemantics\Construction\SourceRanges())->span($at)[1] ?? 0, false);
    }

    /**
     * Lowers a table factor of either grammar generation.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When a factor cannot be published
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a legacy factor has contradictory syntax
     */
    public function factor(Node $factor): Relation
    {
        $form = $this->lowering->form($factor);
        if (isset(self::MODERN[$form->signature])) {
            return $this->lowering->origins->record((new FactorRule($this->lowering))->factor($form), $factor);
        }

        return $this->lowering->origins->record((new LegacyFactorRule($this->lowering))->factor($form), $factor);
    }
}
