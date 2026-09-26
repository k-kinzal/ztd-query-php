<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Derivation\Slice;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use SqlCatalog\Core\Analysis\Derivation\FreeNames;
use SqlCatalog\Core\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Core\Analysis\Derivation\SourceTree;
use SqlCatalog\Core\Analysis\EvaluationBudget;

/**
 * Walks back from a call to the start of its body, keeping only what its argument depends on.
 *
 * The walk starts with the names the argument reads and goes back statement by
 * statement. An assignment to one of those names is kept as a step, and the
 * names its right-hand side reads are looked for instead. A branch that assigns
 * none of them is stepped over whole; one that does splits the path into one
 * path per arm, which is what keeps the values an arm assigns together. A loop
 * becomes one path per number of passes, up to the budget's limit. What comes
 * out at the start of the body is the set of paths, each with the steps that
 * lead from there to the call and the names still left to find.
 *
 * @visibility root
 */
final class BackwardSlicer
{
    /**
     * How many paths are kept apart before the rest are gathered into one.
     */
    public const MAX_PATHS = 24;

    private SourceTree $tree;

    private EvaluationBudget $budget;

    private FreeNames $names;

    private ModifiedNames $modified;

    private LoopPasses $loops;

    private BranchArms $arms;

    private AssignmentSteps $steps;

    /**
     * Wires the slicer to the source it walks and the budget it spends.
     */
    public function __construct(
        SourceTree $tree,
        EvaluationBudget $budget,
        ?FreeNames $names = null,
        ?ModifiedNames $modified = null,
    ) {
        $this->tree = $tree;
        $this->budget = $budget;
        $this->names = $names ?? new FreeNames();
        $this->modified = $modified ?? new ModifiedNames($this->names);
        $this->loops = new LoopPasses($this, $this->names, $this->modified, $budget);
        $this->arms = new BranchArms();
        $this->steps = new AssignmentSteps($this->names, $this->modified);
    }

    /**
     * Every path from the start of the body to the point, with what each still needs.
     *
     * @param array<string, true> $needs The names the value at the point reads
     * @return list<Arrival>
     */
    public function sliceFrom(Node $point, array $needs): array
    {
        $start = [Pending::needing($needs)];
        $anchor = $this->tree->anchorOf($point);
        if ($anchor instanceof FunctionLike) {
            return $this->leave($anchor, $start);
        }

        return $anchor === null ? [new Arrival(null, $start[0])] : $this->before($anchor, $start);
    }

    /**
     * Every path from the start of a body to its end, with what each still needs.
     *
     * @param array<string, true> $needs The names read at the end of the body
     * @return list<Arrival>
     */
    public function sliceFromEnd(FunctionLike $body, array $needs): array
    {
        $statements = array_values($body->getStmts() ?? []);

        return $this->leave($body, $this->walkList($statements, count($statements), [Pending::needing($needs)]));
    }

    /**
     * The paths walked back over everything before a statement, and on out of its list.
     *
     * @param list<Pending> $paths
     * @return list<Arrival>
     */
    public function before(Stmt $statement, array $paths): array
    {
        $location = $this->tree->locate($statement);
        if ($location === null) {
            return $this->arrive($this->tree->bodyOf($statement), $paths);
        }
        [$owner, $list, $index] = $location;

        return $this->leave($owner, $this->walkList($list, $index, $paths));
    }

    /**
     * The paths carried out of the start of a statement list, into whatever owns it.
     *
     * @param list<Pending> $paths
     * @return list<Arrival>
     */
    public function leave(?Node $owner, array $paths): array
    {
        if ($owner === null || $owner instanceof Stmt\Namespace_ || $owner instanceof Stmt\Declare_) {
            return $this->arrive(null, $paths);
        }
        if ($owner instanceof Stmt\ClassMethod || $owner instanceof Stmt\Function_) {
            return $this->arrive($owner, $paths);
        }
        if ($owner instanceof Expr\Closure || $owner instanceof Expr\ArrowFunction) {
            return $this->leaveClosure($owner, $paths);
        }
        if ($owner instanceof Stmt && $this->loops->isLoop($owner)) {
            return $this->loops->leaveBody($owner, $paths);
        }
        if ($owner instanceof Stmt\Finally_) {
            $try = $owner->getAttribute('parent');
            if ($try instanceof Stmt\TryCatch) {
                return $this->before($try, $this->walkList(array_values($try->stmts), count($try->stmts), $paths));
            }
        }
        $statement = $this->owningStatement($owner);

        return $statement === null ? $this->arrive($this->tree->bodyOf($owner), $paths) : $this->before($statement, $paths);
    }

    /**
     * The statement an arm or a block belongs to, which is where the walk goes on from.
     */
    public function owningStatement(Node $owner): ?Stmt
    {
        if ($owner instanceof Stmt\ElseIf_ || $owner instanceof Stmt\Else_ || $owner instanceof Stmt\Case_
            || $owner instanceof Stmt\Catch_) {
            $parent = $owner->getAttribute('parent');

            return $parent instanceof Stmt ? $parent : null;
        }

        return $owner instanceof Stmt ? $owner : null;
    }

    /**
     * The paths carried out of a closure into the body it is written in.
     *
     * What the closure takes from outside — its `use` list, or for an arrow
     * function every name that is not one of its parameters — is looked for
     * where the closure is written. Its parameters, and any name it reads
     * without defining, are its own: a step says so, and the walk does not look
     * for them outside.
     *
     * @param list<Pending> $paths
     * @return list<Arrival>
     */
    public function leaveClosure(Expr\Closure|Expr\ArrowFunction $closure, array $paths): array
    {
        $carried = [];
        foreach ($paths as $path) {
            $outside = $this->outside($closure, $path->needs);
            $own = array_keys(array_diff_key($path->needs, $outside));
            $carried[] = $own === [] ? $path : $path->through(new SliceStep($closure, [], $own), $outside);
        }
        $anchor = $this->tree->anchorOf($closure);
        if ($anchor instanceof FunctionLike) {
            return $this->leave($anchor, $carried);
        }

        return $anchor === null ? $this->arrive(null, $carried) : $this->before($anchor, $carried);
    }

    /**
     * The names among those needed that a closure takes from where it is written.
     *
     * @param array<string, true> $needs
     * @return array<string, true>
     */
    public function outside(Expr\Closure|Expr\ArrowFunction $closure, array $needs): array
    {
        $parameters = [];
        foreach ($closure->params as $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $parameters[$parameter->var->name] = true;
            }
        }
        if ($closure instanceof Expr\ArrowFunction) {
            return array_diff_key($needs, $parameters);
        }
        $uses = [FreeNames::THIS => true];
        foreach ($closure->uses as $use) {
            if (is_string($use->var->name)) {
                $uses[$use->var->name] = true;
            }
        }

        return array_intersect_key($needs, $uses);
    }

    /**
     * The paths that reached the start of a body, as arrivals there.
     *
     * @param list<Pending> $paths
     * @return list<Arrival>
     */
    public function arrive(?FunctionLike $body, array $paths): array
    {
        $named = $body;
        while ($named instanceof Expr\Closure || $named instanceof Expr\ArrowFunction) {
            $named = $this->tree->bodyOf($named);
        }

        return array_map(static fn (Pending $path): Arrival => new Arrival($named, $path), $paths);
    }

    /**
     * The paths walked back over the statements of a list, from the given position to its start.
     *
     * @param list<Stmt> $list
     * @param list<Pending> $paths
     * @return list<Pending>
     */
    public function walkList(array $list, int $end, array $paths): array
    {
        for ($index = $end - 1; $index >= 0; $index--) {
            if ($this->allDone($paths)) {
                break;
            }
            $next = [];
            foreach ($paths as $path) {
                $next = array_merge($next, $path->isDone() ? [$path] : $this->over($list[$index], $path));
            }
            $paths = $this->bound($next);
        }

        return $paths;
    }

    /**
     * Whether no path has anything left to find.
     *
     * @param list<Pending> $paths
     */
    public function allDone(array $paths): bool
    {
        foreach ($paths as $path) {
            if (!$path->isDone()) {
                return false;
            }
        }

        return true;
    }

    /**
     * One path walked back over one statement.
     *
     * @return list<Pending>
     */
    public function over(Stmt $statement, Pending $path): array
    {
        if (!$this->budget->spend()) {
            return [$path->exhaust()];
        }
        if (!$this->modified->touches($statement, $path->needs)) {
            return [$path];
        }
        if ($statement instanceof Stmt\Expression) {
            return [$this->steps->over($statement->expr, $path)];
        }
        if ($statement instanceof Stmt\Return_ && $statement->expr !== null && $this->modified->tracksObjects()) {
            return [$this->steps->over($statement->expr, $path)];
        }
        if ($statement instanceof Stmt\Global_ || $statement instanceof Stmt\Static_ || $statement instanceof Stmt\Unset_) {
            return [$this->steps->declaration($statement, $path)];
        }
        if ($statement instanceof Stmt\Block) {
            return $this->walkList(array_values($statement->stmts), count($statement->stmts), [$path]);
        }
        if ($this->loops->isLoop($statement)) {
            return $this->branch($path, $this->loops->runs($statement, $path->needs));
        }
        $arms = $this->arms->of($statement);
        if ($arms === null) {
            return [$path];
        }
        $paths = $this->branch($path, $this->walkArms($arms, $path->needs));
        if ($statement instanceof Stmt\If_ || $statement instanceof Stmt\Switch_) {
            $paths = array_map(fn (Pending $taken): Pending => $this->steps->over($statement->cond, $taken), $paths);
        }

        return $paths;
    }

    /**
     * Each arm walked back from its end, as paths that start where the branch finishes.
     *
     * @param list<list<Stmt>> $arms
     * @param array<string, true> $needs
     * @return list<Pending>
     */
    public function walkArms(array $arms, array $needs): array
    {
        $runs = [];
        foreach ($arms as $arm) {
            $runs = array_merge($runs, $this->walkList($arm, count($arm), [Pending::needing($needs)]));
        }

        return $runs;
    }

    /**
     * A path taken on over the runs a branch may have taken.
     *
     * The runs become one step that holds them as alternatives, rather than
     * one path each. Running the step still runs every arm on its own, so the
     * values an arm assigns stay together; what is saved is walking the rest of
     * the body once per arm, which for a body of ten consecutive conditionals
     * would be a thousand walks.
     *
     * @param list<Pending> $runs Paths that start where the branch finishes
     * @return list<Pending>
     */
    public function branch(Pending $path, array $runs): array
    {
        if ($runs === []) {
            return [$path];
        }

        return [$path->then(count($runs) === 1 ? $runs[0] : Pending::gather($runs))];
    }

    /**
     * The paths with repeats merged and the rest gathered once there are too many to keep apart.
     *
     * @param list<Pending> $paths
     * @return list<Pending>
     */
    public function bound(array $paths): array
    {
        $unique = [];
        foreach ($paths as $path) {
            $unique[$path->signature()] = $path;
        }
        $paths = array_values($unique);
        if (count($paths) <= self::MAX_PATHS) {
            return $paths;
        }
        $kept = array_slice($paths, 0, self::MAX_PATHS - 1);
        $rest = array_slice($paths, self::MAX_PATHS - 1);
        if ($rest !== []) {
            $kept[] = Pending::gather($rest);
        }

        return $kept;
    }
}
