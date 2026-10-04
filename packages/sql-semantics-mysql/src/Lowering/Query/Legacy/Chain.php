<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
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
 * The server rejects, while parsing, ORDER BY, LIMIT or INTO in an earlier
 * unparenthesized SELECT and PROCEDURE ANALYSE after the first one. The
 * operands are combined left-deep. A lowering-time value. Source:
 * https://dev.mysql.com/doc/refman/5.7/en/union.html ("To apply ORDER BY or
 * LIMIT to an individual SELECT, place the clause inside the parentheses";
 * "Only the last SELECT statement can use INTO OUTFILE"). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class Chain
{
    /**
     * @param non-empty-list<array{Block|Query, Trailer}> $operands The operands in written order, each with the clauses written after it
     * @param list<SetQuantifier|null> $quantifiers The quantifier written after each UNION
     * @param Trailer $trailer The clauses written after the last operand
     */
    public function __construct(public readonly array $operands, public readonly array $quantifiers, public readonly Trailer $trailer = new Trailer())
    {
    }

    /**
     * Combines the operands into one query.
     *
     * @throws AnalysisException When an operand writes a clause the server rejects there
     * @throws ImplementationGap When the last SELECT of a union writes its own clauses before those of the union
     */
    public function query(): Query
    {
        $operands = $this->operands;
        $last = count($operands) - 1;
        [$final, $after] = $operands[$last];
        if ($final instanceof Block && !$final->accepts($after)) {
            if ($last > 0) {
                throw ImplementationGap::rule('a union whose last SELECT writes ORDER BY, LIMIT, INTO or locking clauses before the ORDER BY or LIMIT of the union');
            }
            $final = $final->select();
        }
        $trailer = ($final instanceof Block ? $final->trailer->then($after) : $after)->then($this->trailer);
        if ($last === 0) {
            return $final instanceof Block ? $final->bare()->then($trailer)->select() : $trailer->wrap($final);
        }
        $operands[$last] = [$final instanceof Block ? $final->bare() : $final, new Trailer()];
        $query = null;
        foreach ($operands as $index => [$operand, $clauses]) {
            if ($operand instanceof Block && $operand->accepts($clauses)) {
                $operand = $operand->then($clauses);
                if ($index < $last) {
                    $this->check($operand, $index);
                }
                $operand = $operand->select();
            } else {
                $operand = $clauses->wrap($operand instanceof Block ? $operand->select() : $operand);
            }
            $query = $query === null ? $operand : new SetOperation($query, SetOperator::Union, $this->quantifiers[$index - 1] ?? null, $operand);
        }

        return $trailer->wrap($query);
    }

    /**
     * Rejects the clauses the server does not accept in an operand before the last one.
     *
     * @throws AnalysisException When the operand writes such a clause
     */
    public function check(Block $operand, int $index): void
    {
        $trailer = $operand->trailer;
        if ($trailer->orderBy !== [] || $trailer->limit !== null) {
            throw new AnalysisException('Incorrect usage of UNION and ORDER BY or LIMIT: only the last SELECT of a union takes them without parentheses.');
        }
        if ($trailer->into !== null || $operand->into !== null) {
            throw new AnalysisException('Incorrect usage of UNION and INTO: only the last SELECT of a union takes INTO.');
        }
        if ($trailer->procedure !== null && $index > 0) {
            throw new AnalysisException('Incorrect usage of PROCEDURE and UNION: PROCEDURE ANALYSE follows the first SELECT only.');
        }
    }
}
