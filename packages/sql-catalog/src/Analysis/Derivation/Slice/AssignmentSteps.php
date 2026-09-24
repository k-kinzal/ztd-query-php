<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation\Slice;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use SqlCatalog\Analysis\Effect\WriteEffects;

/**
 * Walks a path back over what one statement assigns.
 *
 * An assignment to a name the path needs becomes a step, and the names its
 * right-hand side reads are needed instead. An assignment that replaces its
 * target settles the name; one that only adds to it, like `.=` or `$a[] =`,
 * keeps the name needed so the walk goes on to find what it added to.
 *
 * @visibility root
 */
final class AssignmentSteps
{
    private FreeNames $names;

    private ModifiedNames $modified;

    /**
     * Wires the walk to what tells it which names an assignment reads and writes.
     */
    public function __construct(FreeNames $names, ModifiedNames $modified)
    {
        $this->names = $names;
        $this->modified = $modified;
    }

    /**
     * One path walked back over the assignments an expression makes, last first.
     */
    public function over(Expr $expression, Pending $path): Pending
    {
        foreach (array_reverse($this->within($expression)) as $assignment) {
            $written = $this->modified->of($assignment);
            if (!$this->modified->touches($assignment, $path->needs)) {
                continue;
            }
            $replaced = $assignment instanceof Expr\Assign || $assignment instanceof Expr\AssignRef ? $this->modified->targets($assignment->var) : $this->modified->own($assignment);
            $needs = $this->replaces($assignment) && !isset($written[WriteEffects::ALL]) ? array_diff_key($path->needs, $replaced) : $path->needs;
            $path = $path->through(new SliceStep($assignment), $needs + $this->names->read($assignment));
        }

        return $path;
    }

    /**
     * Whether an assignment replaces what its target held rather than adding to it.
     */
    public function replaces(Expr $assignment): bool
    {
        return $assignment instanceof Expr\Assign && !$assignment->var instanceof Expr\ArrayDimFetch;
    }

    /**
     * The assignments an expression makes, in the order they run.
     *
     * @return list<Expr>
     */
    public function within(Node $node): array
    {
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            return [];
        }
        if ($node instanceof Expr && ($this->modified->tracksObjects() || $this->modified->own($node) !== []
            || (($node instanceof Expr\Ternary || $node instanceof Expr\Match_ || $node instanceof Expr\BinaryOp)
                && $this->modified->of($node) !== []))) {
            return [$node];
        }
        $found = [];
        foreach (get_object_vars($node) as $sub) {
            foreach (is_array($sub) ? $sub : [$sub] as $child) {
                if ($child instanceof Node) {
                    $found = array_merge($found, $this->within($child));
                }
            }
        }

        return $found;
    }

    /**
     * One path walked back over a `global`, `static` or `unset` that defines names it needs.
     */
    public function declaration(Stmt\Global_|Stmt\Static_|Stmt\Unset_ $statement, Pending $path): Pending
    {
        $written = $this->modified->own($statement);
        $needs = array_diff_key($path->needs, $written);
        if ($statement instanceof Stmt\Unset_) {
            $needs = $path->needs;
        }

        return $path->through(new SliceStep($statement), $needs);
    }
}
