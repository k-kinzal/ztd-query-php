<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\Value\Term;

/**
 * Immutable lazy bindings for one declaration and call context.
 * @visibility root
 */
final class Frame
{
    /**
     * @param array<string, Term|Binding> $bindings Only demanded bindings are expanded
     * @param array<string, Term> $properties Explicit receiver state
     * @param array<string, int> $calls Call sites already on this dependency branch
     * @param array<int, int> $iterations Selected versions of loop-carried definitions
     */
    public function __construct(
        public readonly Graph $graph,
        public readonly string $identity,
        public readonly array $bindings = [],
        public readonly array $properties = [],
        public readonly array $calls = [],
        public readonly bool $invocation = false,
        public readonly array $iterations = [],
        /**
         * Query-local dependency expansion accounting.
         */
        public readonly ?string $baseIdentity = null,
        public readonly ?Binding $origin = null,
        public readonly string $calledClass = '',
    ) {
    }

    /**

     * Selects one loop-carried definition version without advancing application state.

     */
    public function iteration(int $header, int $iteration): self
    {
        $versions = $this->iterations;
        $versions[$header] = $iteration;
        ksort($versions);
        $base = $this->baseIdentity ?? $this->identity;
        return new self($this->graph, $base . ':loops:' . serialize($versions), $this->bindings, $this->properties, $this->calls, $this->invocation, $versions, $base, $this->origin, $this->calledClass);
    }
}
