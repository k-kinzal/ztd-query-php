<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Set;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Query\QueryTails;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

/**
 * The operands of a 5.x union before a later UNION, when the last of them is a SELECT that keeps its own LIMIT or locking clauses.
 *
 * A 5.x union is a list of query blocks, each with its own clauses. A
 * SELECT written without parentheses before another UNION keeps its own
 * locking clauses (`select_lock_type` sets the lock of the tables of that
 * block) and, in 5.6, its own LIMIT (`mysql_new_select` refuses only an
 * ORDER BY there; 5.7 `LEX::new_union_query` refuses the LIMIT too). The
 * same SELECT written last would give its clauses to the whole union, so
 * this form is not a query on its own: it is only the left operand of a
 * further UNION (`SetOperation`, `OrderedSetOperation` or another leading
 * union), never a body, a subquery or a statement. The operands are
 * combined to the left like every set operation.
 *
 * Rule: MYSQL-LEADING-UNION-001. The output is derived by
 * MYSQL-SET-FACTS-001 for the operation that continues it; the form needs
 * MySQL 5.6 or 5.7, and its own LIMIT needs 5.6. Source:
 * https://dev.mysql.com/doc/refman/5.6/en/union.html,
 * https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_parse.cc (`mysql_new_select`),
 * https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_lex.cc (`LEX::new_union_query`).
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a last operand without clauses of its own
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
 *     $two = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion($one, null, $two) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction
 */
final class LeadingUnion implements Node
{
    use Snapshot;

    /**
     * @param Query|LeadingUnion $left The operands before the last one
     * @param SetQuantifier|null $quantifier The written DISTINCT or ALL
     * @param Select $right The last operand, with its own LIMIT or locking clauses and nothing else after its items
     * @throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction When the last operand keeps no such clause or another one, or the left operand is written in parentheses as a set operand
     */
    public function __construct(public readonly Query|LeadingUnion $left, public readonly ?SetQuantifier $quantifier, public readonly Select $right)
    {
        Check::input($right->trailed() && $right->orderBy === [] && $right->procedure === null && $right->into === null && $right->late === null, 'The last operand of a leading union keeps its own LIMIT or locking clauses and nothing else.');
        Check::input(!$left instanceof SetOperation || $left->operator === SetOperator::Union, 'A leading union continues a UNION.');
        Check::input(!$left instanceof QueryStatement && !$left instanceof OrderedSetOperation && !($left instanceof QueryExpression && $left->with !== null), 'A query with a WITH clause, INTO, locking clauses or an ordering of its result is written in parentheses as a set operand.');
    }

    /**
     * Writes the operands around UNION.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        (new QueryTails())->leading($this, $out);
    }
}
