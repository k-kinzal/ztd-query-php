<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use SqlCatalog\Analysis\Derivation\Objects\ObjectEffects;
use SqlCatalog\Analysis\Effect\ReferenceEffects;
use SqlCatalog\Analysis\Effect\WriteEffects;
use WeakMap;

/**
 * The names a statement may assign, wherever inside it the assignment is written.
 *
 * Walking back over a statement that assigns none of the names still being
 * looked for learns nothing, so this is what decides whether a branch has to be
 * split into its arms at all. The answer is remembered per statement, because
 * the same statement is walked back over once for every path that crosses it.
 *
 * @visibility root
 */
final class ModifiedNames
{
    /**
     * @var WeakMap<Node, array<string, true>>
     */
    private WeakMap $remembered;

    private FreeNames $names;

    private ?ReferenceEffects $references = null;

    /**
     * Builds the reader over the naming the rest of the derivation uses.
     */
    public function __construct(?FreeNames $names = null, private readonly ?ObjectEffects $objects = null)
    {
        $this->names = $names ?? new FreeNames();
        $this->remembered = new WeakMap();
    }

    /**
     * Whether the node may assign any of the given names.
     *
     * @param array<string, true> $wanted
     */
    public function touches(Node $node, array $wanted): bool
    {
        $written = $this->of($node);

        return $wanted !== [] && (isset($written[WriteEffects::ALL]) || array_intersect_key($written, $wanted) !== []);
    }

    /**
     * Every name the node may assign.
     *
     * @return array<string, true>
     */
    public function of(Node $node): array
    {
        $known = $this->remembered[$node] ?? null;
        if ($known !== null) {
            return $known;
        }
        $names = $this->collect($node);
        $this->remembered[$node] = $names;

        return $names;
    }

    /**
     * Every name the node may assign, worked out from scratch.
     *
     * @return array<string, true>
     */
    public function collect(Node $node): array
    {
        if ($node instanceof Stmt\Function_ || $node instanceof Stmt\ClassLike || $node instanceof Expr\Closure) {
            return [];
        }
        $names = $this->own($node);
        $this->references ??= new ReferenceEffects();
        $names += $this->references->affected($node, $names);
        foreach (get_object_vars($node) as $sub) {
            foreach (is_array($sub) ? $sub : [$sub] as $child) {
                if ($child instanceof Node) {
                    $names += $this->of($child);
                }
            }
        }

        return $names;
    }

    /**
     * The names the node itself assigns, leaving aside what its children assign.
     *
     * @return array<string, true>
     */
    public function own(Node $node): array
    {
        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef) {
            return $this->targets($node->var) + (new WriteEffects())->own($node);
        }
        if ($node instanceof Expr\PreInc || $node instanceof Expr\PostInc
            || $node instanceof Expr\PreDec || $node instanceof Expr\PostDec) {
            return $this->targets($node->var);
        }
        if ($node instanceof Stmt\Global_ || $node instanceof Stmt\Unset_) {
            return $this->all($node->vars);
        }
        if ($node instanceof Stmt\Static_) {
            $names = [];
            foreach ($node->vars as $static) {
                $names += $this->targets($static->var);
            }

            return $names;
        }
        if ($node instanceof Stmt\Foreach_) {
            return $this->targets($node->valueVar) + ($node->keyVar === null ? [] : $this->targets($node->keyVar));
        }
        if ($node instanceof Stmt\Catch_ && $node->var !== null) {
            return $this->targets($node->var);
        }

        $written = (new WriteEffects())->own($node)
            + ($node instanceof Expr\CallLike ? ($this->objects?->writes($node) ?? []) : []);
        $this->references ??= new ReferenceEffects();

        return $written + ($node instanceof Expr\CallLike ? $this->references->affected($node, $written) : []);
    }

    /**
     * Whether object calls are retained as whole expression steps.
     */
    public function tracksObjects(): bool
    {
        return $this->objects !== null;
    }

    /**
     * The names written by assigning to the given target.
     *
     * @return array<string, true>
     */
    public function targets(Node $target): array
    {
        if ($target instanceof Expr\ArrayDimFetch && $this->objects !== null) {
            return $this->targets($target->var);
        }
        if ($target instanceof Expr\PropertyFetch && $this->objects !== null && $this->names->propertyName($target) === null) {
            return $this->objects->writes($target);
        }
        $base = $this->baseName($target);
        if ($base !== null) {
            return [$base => true];
        }
        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            $names = [];
            foreach ($target->items as $item) {
                if ($item !== null) {
                    $names += $this->targets($item->value);
                }
            }

            return $names;
        }

        return [];
    }

    /**
     * The name an assignment target writes into, looking through element writes.
     *
     * `$sql['where'][] = …` writes into `sql`; `$this->parts[] = …` writes into
     * `this->parts`. Anything else, such as a variable variable, has no name.
     */
    public function baseName(Node $target): ?string
    {
        if ($target instanceof Expr\ArrayDimFetch) {
            return $this->baseName($target->var);
        }
        if ($target instanceof Expr\Variable) {
            return is_string($target->name) ? $target->name : null;
        }

        return $this->names->propertyName($target);
    }

    /**
     * The names written by each of the given targets.
     *
     * @param array<array-key, Node> $targets
     * @return array<string, true>
     */
    public function all(array $targets): array
    {
        $names = [];
        foreach ($targets as $target) {
            $names += $this->targets($target);
        }

        return $names;
    }
}
