<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Derivation\Slice;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use SqlCatalog\Core\Analysis\Derivation\FreeNames;
use SqlCatalog\Core\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Core\Analysis\EvaluationBudget;

/**
 * Walks back through a loop one pass at a time.
 *
 * A loop that builds part of a statement produces a different statement for
 * every number of times it runs, so each number of passes is its own path: the
 * statement as it is before the loop, after one pass, after two, up to the
 * budget's limit. When a path could still have gone round again at the limit,
 * it is marked as cut short, because the statements listed are not all of the
 * ones the loop can produce. A `foreach` over an array written out in full runs
 * exactly as many times as the array has elements, and only that number is
 * taken.
 *
 * @visibility root
 */
final class LoopPasses
{
    private BackwardSlicer $slicer;

    private FreeNames $names;

    private ModifiedNames $modified;

    private EvaluationBudget $budget;

    private AssignmentSteps $steps;

    /**
     * Wires the loop walk to the slicer it walks bodies with.
     */
    public function __construct(
        BackwardSlicer $slicer,
        FreeNames $names,
        ModifiedNames $modified,
        EvaluationBudget $budget,
    ) {
        $this->slicer = $slicer;
        $this->names = $names;
        $this->modified = $modified;
        $this->budget = $budget;
        $this->steps = new AssignmentSteps($names, $modified);
    }

    /**
     * Whether the node is a loop.
     */
    public function isLoop(Node $node): bool
    {
        return $node instanceof Stmt\While_ || $node instanceof Stmt\Do_
            || $node instanceof Stmt\For_ || $node instanceof Stmt\Foreach_;
    }

    /**
     * The runs a loop before the point may have made, as paths that start where the loop finishes.
     *
     * @param array<string, true> $needs
     * @return list<Pending>
     */
    public function runs(Stmt $loop, array $needs): array
    {
        $exact = $this->writtenPasses($loop);
        $runs = [];
        $current = [Pending::needing($needs)];
        if (!$loop instanceof Stmt\Do_ && ($exact === null || $exact === 0)) {
            $runs = $this->entering($loop, $current);
        }
        for ($pass = 1; $pass <= $this->budget->maxLoopPasses; $pass++) {
            $current = $this->pass($loop, $current);
            if ($exact === null || $exact === $pass) {
                $runs = array_merge($runs, $this->entering($loop, $current));
            }
            if (!$this->continues($loop, $current)) {
                return $runs;
            }
        }
        if ($exact !== null && $exact <= $this->budget->maxLoopPasses) {
            return $runs;
        }

        return array_map(static fn (Pending $run): Pending => $run->cut(), $runs);
    }

    /**
     * The paths carried out of the start of a loop body the point is written in.
     *
     * @param list<Pending> $paths Paths standing at the start of the pass the point is in
     * @return list<Arrival>
     */
    public function leaveBody(Stmt $loop, array $paths): array
    {
        $arrivals = [];
        $current = array_map(fn (Pending $path): Pending => $this->binding($loop, $path), $paths);
        for ($pass = 0; ; $pass++) {
            $continues = $this->continues($loop, $current);
            $last = $pass >= $this->budget->maxLoopPasses || !$continues;
            $entered = $last && $continues ? array_map(static fn (Pending $path): Pending => $path->cut(), $current) : $current;
            $arrivals = array_merge($arrivals, $this->slicer->before($loop, $this->entering($loop, $entered)));
            if ($last) {
                return $arrivals;
            }
            $current = $this->pass($loop, $current);
        }
    }

    /**
     * The paths walked back over one whole pass, from its end to its start.
     *
     * @param list<Pending> $paths
     * @return list<Pending>
     */
    public function pass(Stmt $loop, array $paths): array
    {
        if ($loop instanceof Stmt\For_) {
            $paths = $this->expressions($loop->loop, $paths);
        }
        $body = $this->bodyOf($loop);
        $paths = $this->slicer->walkList($body, count($body), $paths);

        return array_map(fn (Pending $path): Pending => $this->binding($loop, $path), $paths);
    }

    /**
     * The statements a loop repeats.
     *
     * @return list<Stmt>
     */
    public function bodyOf(Stmt $loop): array
    {
        if ($loop instanceof Stmt\While_ || $loop instanceof Stmt\Do_
            || $loop instanceof Stmt\For_ || $loop instanceof Stmt\Foreach_) {
            return array_values($loop->stmts);
        }

        return [];
    }

    /**
     * A path walked back over the start of one pass of a `foreach`, where its variables are set.
     */
    public function binding(Stmt $loop, Pending $path): Pending
    {
        if (!$loop instanceof Stmt\Foreach_ || $path->isDone()) {
            return $path;
        }
        $written = $this->modified->own($loop);
        if (array_intersect_key($written, $path->needs) === []) {
            return $path;
        }

        return $path->through(new SliceStep($loop), array_diff_key($path->needs, $written));
    }

    /**
     * The paths walked back over what runs before a loop's first pass.
     *
     * A `foreach` reads the array it goes over once, before the first pass,
     * whatever the passes then do to the variable holding it; so that is where
     * the array is looked for, and only on a path that needed the loop's
     * variables at all.
     *
     * @param list<Pending> $paths
     * @return list<Pending>
     */
    public function entering(Stmt $loop, array $paths): array
    {
        if ($loop instanceof Stmt\For_) {
            return $this->expressions($loop->init, $paths);
        }
        if (!$loop instanceof Stmt\Foreach_) {
            return $paths;
        }

        return array_map(
            fn (Pending $path): Pending => $this->bound($loop, $path)
                ? new Pending($path->steps, $path->needs + $this->names->read($loop->expr), $path->truncated, $path->exhausted)
                : $path,
            $paths,
        );
    }

    /**
     * Whether a path took one of a `foreach`'s passes as a step.
     */
    public function bound(Stmt\Foreach_ $loop, Pending $path): bool
    {
        foreach ($path->steps as $step) {
            if ($step->node === $loop) {
                return true;
            }
        }

        return false;
    }

    /**
     * The paths walked back over a list of expressions, last first.
     *
     * @param array<array-key, Expr> $expressions
     * @param list<Pending> $paths
     * @return list<Pending>
     */
    public function expressions(array $expressions, array $paths): array
    {
        foreach (array_reverse($expressions) as $expression) {
            $paths = array_map(
                fn (Pending $path): Pending => $path->isDone() ? $path : $this->steps->over($expression, $path),
                $paths,
            );
        }

        return $paths;
    }

    /**
     * Whether another pass could still change something a path needs.
     *
     * @param list<Pending> $paths
     */
    public function continues(Stmt $loop, array $paths): bool
    {
        foreach ($paths as $path) {
            if (!$path->isDone() && $this->modified->touches($loop, $path->needs)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many times a `foreach` over an array written out in full runs, or null when that is not known.
     */
    public function writtenPasses(Stmt $loop): ?int
    {
        if (!$loop instanceof Stmt\Foreach_ || !$loop->expr instanceof Expr\Array_) {
            return null;
        }
        foreach ($loop->expr->items as $item) {
            if ($item->unpack) {
                return null;
            }
        }

        return count($loop->expr->items);
    }
}
