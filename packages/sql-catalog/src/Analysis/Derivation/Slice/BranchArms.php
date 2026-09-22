<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation\Slice;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * The runs of statements a branching statement can take.
 *
 * An if, a switch and a try each run one of several lists of statements. Each
 * list is one arm; an arm that cannot fall through to the next statement is
 * left out, since it cannot be the one taken on the way to anything after it.
 *
 * @visibility root
 */
final class BranchArms
{
    /**
     * The runs of statements a branching statement may have taken, or null when it does not branch.
     *
     * An arm that cannot finish — it returns, throws, breaks or continues —
     * cannot have been the one taken on the way to a statement after it, so
     * only the arms that fall through are kept.
     *
     * @return list<list<Stmt>>|null
     */
    public function of(Stmt $statement): ?array
    {
        $arms = match (true) {
            $statement instanceof Stmt\If_ => $this->ifArms($statement),
            $statement instanceof Stmt\Switch_ => $this->switchArms($statement),
            $statement instanceof Stmt\TryCatch => $this->tryArms($statement),
            default => null,
        };
        if ($arms === null) {
            return null;
        }
        $open = array_values(array_filter($arms, fn (array $arm): bool => $this->completes($arm)));

        return $open === [] ? $arms : $open;
    }

    /**
     * The arms of a conditional, with the empty arm taken when no condition holds.
     *
     * @return list<list<Stmt>>
     */
    public function ifArms(Stmt\If_ $statement): array
    {
        $arms = [array_values($statement->stmts)];
        foreach ($statement->elseifs as $elseif) {
            $arms[] = array_values($elseif->stmts);
        }
        $arms[] = $statement->else === null ? [] : array_values($statement->else->stmts);

        return $arms;
    }

    /**
     * The arms of a switch, each without the break that ends it.
     *
     * @return list<list<Stmt>>
     */
    public function switchArms(Stmt\Switch_ $statement): array
    {
        $arms = [];
        $defaulted = false;
        foreach ($statement->cases as $case) {
            $defaulted = $defaulted || $case->cond === null;
            $body = array_values($case->stmts);
            $last = $body === [] ? null : $body[count($body) - 1];
            $arms[] = $last instanceof Stmt\Break_ ? array_slice($body, 0, -1) : $body;
        }
        if (!$defaulted) {
            $arms[] = [];
        }

        return $arms;
    }

    /**
     * The runs of a try statement: the try block, or one of its handlers, each followed by the finally block.
     *
     * @return list<list<Stmt>>
     */
    public function tryArms(Stmt\TryCatch $statement): array
    {
        $finally = $statement->finally === null ? [] : array_values($statement->finally->stmts);
        $arms = [array_merge(array_values($statement->stmts), $finally)];
        foreach ($statement->catches as $catch) {
            $arms[] = array_merge(array_values($catch->stmts), $finally);
        }

        return $arms;
    }

    /**
     * Whether a run of statements can finish and let the next statement run.
     *
     * @param list<Stmt> $statements
     */
    public function completes(array $statements): bool
    {
        $last = $statements === [] ? null : $statements[count($statements) - 1];
        if ($last instanceof Stmt\Return_ || $last instanceof Stmt\Break_ || $last instanceof Stmt\Continue_
            || $last instanceof Stmt\Goto_) {
            return false;
        }

        return !($last instanceof Stmt\Expression && ($last->expr instanceof Expr\Throw_ || $last->expr instanceof Expr\Exit_));
    }
}
