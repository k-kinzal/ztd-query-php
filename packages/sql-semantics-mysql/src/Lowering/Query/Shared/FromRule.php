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
use SqlSemantics\Statement\Relation;

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
     */
    public function from(Node $clause): ?Relation
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_from_clause:' => null,
            'opt_from_clause: from_clause' => $this->from($form->node(0)),
            'from_clause: FROM from_tables', 'from_clause: FROM table_reference_list' => $this->tables($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the tables of a FROM clause, of UPDATE or of DELETE: one reference, a comma list or DUAL.
     *
     * @throws ImplementationGap When a production has no rule
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
     */
    public function members(Node $tables): array
    {
        $form = $this->lowering->form($tables);
        if ($form->signature === 'from_tables: DUAL_SYM' || $form->signature === 'table_reference_list: DUAL_SYM') {
            return [new Dual()];
        }
        if ($form->signature === 'from_tables: table_reference_list') {
            return $this->members($form->node(0));
        }
        if ($form->signature === 'table_reference_list: join_table_list' || $form->signature === 'join_table_list: derived_table_list') {
            return (new JoinRule($this->lowering))->list($form->node(0));
        }
        if ($form->signature !== 'table_reference_list: table_reference' && $form->signature !== 'table_reference_list: table_reference_list , table_reference') {
            throw ImplementationGap::production($form);
        }
        $members = [];
        foreach ((new Lists())->items($tables) as $item) {
            $members[] = (new ReferenceRule($this->lowering))->reference($item);
        }

        return $members;
    }

    /**
     * Lowers a table factor of either grammar generation.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function factor(Node $factor): Relation
    {
        $form = $this->lowering->form($factor);
        if (isset(self::MODERN[$form->signature])) {
            return (new FactorRule($this->lowering))->factor($form);
        }

        return (new LegacyFactorRule($this->lowering))->factor($form);
    }
}
