<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Derivation\Slice;

use PhpParser\Node;

/**
 * One thing that has to happen, on the way to the call, for its argument to have the value it has.
 *
 * A step is the node that does it: an assignment, a `global` or `static`
 * declaration, an `unset`, the start of one pass through a `foreach`, or a
 * closure parameter taking whatever a caller passes. A step can instead hold
 * alternatives — whole runs of steps, any one of which may have happened —
 * when the paths had to be gathered up to keep their number bounded. Entering
 * a closure is a step as well: the names it lists are the ones the closure
 * defines for itself, as parameters or not at all, rather than taking them
 * from the body it is written in.
 *
 * @visibility root
 */
final class SliceStep
{
    /**
     * @param Node|null $node What happens, or null for a step made of alternatives
     * @param list<list<SliceStep>> $alternatives The runs any one of which happened, each in the order it runs
     * @param list<string> $names For entering a closure, the names the closure does not take from outside
     */
    public function __construct(
        public readonly ?Node $node,
        public readonly array $alternatives = [],
        public readonly array $names = [],
    ) {
    }

    /**
     * A step that is one of several runs of steps.
     *
     * @param list<list<SliceStep>> $alternatives
     */
    public static function either(array $alternatives): self
    {
        return new self(null, $alternatives);
    }

    /**
     * What tells this step apart from another, for recognising the same path reached twice.
     */
    public function signature(): string
    {
        return $this->node !== null ? (string) spl_object_id($this->node) : 'either' . spl_object_id($this);
    }
}
