<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Statement\Query;

/**
 * The operands of a 5.x UNION in written order, the quantifiers between them and the clauses written after the last one.
 *
 * Rule: MYSQL-UNION-LEGACY-001. The ORDER BY, LIMIT, INTO and locking
 * clauses written inside the last unparenthesized SELECT of a union apply
 * to the whole union, so they are taken out of that block; with the
 * clauses written after a last parenthesized operand they wrap the union.
 * In a 5.6 subquery an ORDER BY or LIMIT written after the last SELECT
 * applies to the union instead, and the last SELECT keeps its own clauses
 * (the `union_order_or_limit` action makes the fake query block the global
 * parameters). The server rejects, while parsing, ORDER BY or INTO in an
 * earlier unparenthesized SELECT, LIMIT there in 5.7 (5.6 keeps it as the
 * block's own), an ORDER BY or LIMIT a 5.6 subquery writes after an earlier
 * SELECT other than a LIMIT after the first one, and PROCEDURE ANALYSE in a
 * union. An earlier SELECT keeps its own locking clauses and, in 5.6, its
 * own LIMIT; when it is not the first operand the operands up to it form a
 * leading union (MYSQL-LEADING-UNION-001). The operands are combined
 * left-deep. A lowering-time value.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/union.html ("To apply
 * ORDER BY or LIMIT to an individual SELECT, place the clause inside the
 * parentheses"; "Only the last SELECT statement can use INTO OUTFILE"),
 * https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_parse.cc
 * (`mysql_new_select`), https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_lex.cc
 * (`LEX::new_union_query`). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class Chain
{
    /**
     * @param non-empty-list<array{Block|Query, Trailer}> $operands The operands in written order, each with the clauses written after it
     * @param list<SetQuantifier|null> $quantifiers The quantifier written after each UNION
     * @param GrammarRelease $release The grammar release the union is written in
     * @param Trailer $trailer The clauses written after the last operand
     */
    public function __construct(public readonly array $operands, public readonly array $quantifiers, public readonly GrammarRelease $release, public readonly Trailer $trailer = new Trailer())
    {
    }

    /**
     * Combines the operands into one query.
     *
     * @throws AnalysisException When the last SELECT writes PROCEDURE ANALYSE (ER_WRONG_USAGE of the 5.6 `procedure_analyse_clause` action, which refuses any block but the first, and of 5.7 `PT_procedure_analyse::contextualize`), or an earlier operand writes a clause the server rejects there
     * @throws ImplementationGap When clauses cannot follow their query block
     */
    public function query(): Query
    {
        $operands = $this->operands;
        $last = count($operands) - 1;
        [$final, $after] = $operands[$last];
        $after = $after->then($this->trailer);
        if ($last === 0) {
            return $final instanceof Block ? $final->finish($after) : $after->wrap($final);
        }
        $query = null;
        foreach (array_slice($operands, 0, $last) as $index => [$operand, $clauses]) {
            $operand = $operand instanceof Block ? $this->earlier($operand, $clauses, $index) : $this->enclosed($operand, $clauses, $index);
            $query = match (true) {
                $query === null => $operand,
                $operand instanceof Select && $operand->trailed() => new LeadingUnion($query, $this->quantifiers[$index - 1] ?? null, $operand),
                default => new SetOperation($query, SetOperator::Union, $this->quantifiers[$index - 1] ?? null, $operand),
            };
        }
        $quantifier = $this->quantifiers[$last - 1] ?? null;
        if (!$final instanceof Block) {
            return $after->wrap(new SetOperation($query, SetOperator::Union, $quantifier, $final));
        }
        if ($final->trailer->procedure !== null) {
            throw new AnalysisException('Incorrect usage of PROCEDURE and UNION: PROCEDURE ANALYSE follows a single query block only.');
        }
        if (!$after->empty() && !$final->trailer->empty()) {
            return new OrderedSetOperation($query, SetOperator::Union, $quantifier, $final->select(), $after->orderBy, $after->limit);
        }

        return $final->trailer->then($after)->wrap(new SetOperation($query, SetOperator::Union, $quantifier, $final->bare()->select()));
    }

    /**
     * Finishes an operand before the last one, rejecting the clauses the server does not accept there.
     *
     * @throws AnalysisException When the operand writes such a clause: ER_WRONG_USAGE "UNION and ORDER BY|LIMIT|INTO|PROCEDURE ANALYSE" of `add_select_to_union_list` and `mysql_new_select` (5.6, run by the `union_list` action) and of `LEX::new_union_query` (5.7, run while contextualizing the union), or ER_SYNTAX_ERROR when the clauses written after an earlier SELECT made the fake query block current (`add_select_to_union_list` refuses GLOBAL_OPTIONS_TYPE)
     */
    public function earlier(Block $operand, Trailer $clauses, int $index): Query
    {
        $trailer = $operand->trailer;
        if ($trailer->orderBy !== [] || ($trailer->limit !== null && $this->release !== GrammarRelease::MySql5651)) {
            throw new AnalysisException('Incorrect usage of UNION and ORDER BY or LIMIT: only the last SELECT of a union takes them without parentheses.');
        }
        if ($trailer->into !== null || $operand->into !== null) {
            throw new AnalysisException('Incorrect usage of UNION and INTO: only the last SELECT of a union takes INTO.');
        }
        if ($trailer->procedure !== null) {
            throw new AnalysisException('Incorrect usage of UNION and SELECT ... PROCEDURE ANALYSE(): a union takes no PROCEDURE ANALYSE.');
        }
        if ($clauses->orderBy !== [] || ($index > 0 && !$clauses->empty())) {
            throw new AnalysisException('Syntax error: only a LIMIT follows the first SELECT of a union in a subquery.');
        }

        return $operand->finish($clauses);
    }

    /**
     * Finishes a parenthesized operand before the last one with the ORDER BY and LIMIT a 5.x subquery writes after it.
     *
     * After the first operand the clauses belong to the query block in the
     * parentheses, unless an ORDER BY follows a block that orders or limits
     * its rows itself: the `order_clause` action (5.6) and
     * `PT_order::contextualize` (5.7) then make a fake query block current.
     * After a later operand the fake query block of the union is current.
     * The next UNION refuses a current fake query block.
     *
     * @throws AnalysisException When the clauses made the fake query block current: ER_SYNTAX_ERROR of `add_select_to_union_list` (5.6, GLOBAL_OPTIONS_TYPE) and ER_WRONG_USAGE "UNION and ORDER BY|LIMIT" of `LEX::new_union_query` (5.7)
     */
    public function enclosed(Query $operand, Trailer $clauses, int $index): Query
    {
        $inner = $operand;
        while ($inner instanceof ParenthesizedQuery) {
            $inner = $inner->query;
        }
        $ordered = $inner instanceof Select && ($inner->orderBy !== [] || $inner->limit !== null);
        if (($index > 0 && !$clauses->empty()) || ($clauses->orderBy !== [] && $ordered)) {
            throw new AnalysisException('Syntax error: the ORDER BY or LIMIT after this parenthesized SELECT applies to the whole union, so no UNION follows it.');
        }

        return $clauses->wrap($operand);
    }
}
