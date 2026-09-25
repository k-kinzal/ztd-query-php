<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Derivation\Slice;

/**
 * One path being walked back from a call: what it passed through, and what it still needs.
 *
 * The steps are held nearest-first, in the order the walk finds them, and
 * turned around only when the path is run. What is still needed is the set of
 * names no step on the path has defined yet; when it is empty, nothing earlier
 * in the program can change the argument and the walk along this path is done.
 *
 * @visibility root
 */
final class Pending
{
    /**
     * @param list<SliceStep> $steps The steps found so far, nearest to the call first
     * @param array<string, true> $needs The names nothing on the path has defined yet
     * @param bool $truncated Whether a bound cut the path short of every way it could have gone
     * @param bool $exhausted Whether the budget ran out before the path was walked to its start
     */
    public function __construct(
        public readonly array $steps,
        public readonly array $needs,
        public readonly bool $truncated = false,
        public readonly bool $exhausted = false,
    ) {
    }

    /**
     * A path that has gone nowhere yet, needing the given names.
     *
     * @param array<string, true> $needs
     */
    public static function needing(array $needs): self
    {
        return new self([], $needs);
    }

    /**
     * The path, one step further back.
     *
     * @param array<string, true> $needs What the path needs once the step is taken
     */
    public function through(SliceStep $step, array $needs): self
    {
        return new self(array_merge($this->steps, [$step]), $needs, $this->truncated, $this->exhausted);
    }

    /**
     * The path, followed further back by another path that started where this one stands.
     */
    public function then(self $further): self
    {
        return new self(
            array_merge($this->steps, $further->steps),
            $further->needs,
            $this->truncated || $further->truncated,
            $this->exhausted || $further->exhausted,
        );
    }

    /**
     * The same path, marked as cut short by a bound.
     */
    public function cut(): self
    {
        return new self($this->steps, $this->needs, true, $this->exhausted);
    }

    /**
     * The same path, marked as stopped by the budget.
     */
    public function exhaust(): self
    {
        return new self($this->steps, $this->needs, $this->truncated, true);
    }

    /**
     * Whether nothing earlier can change what the path needs.
     */
    public function isDone(): bool
    {
        return $this->needs === [] || $this->exhausted;
    }

    /**
     * The steps in the order they run.
     *
     * @return list<SliceStep>
     */
    public function forward(): array
    {
        return array_reverse($this->steps);
    }

    /**
     * What tells this path apart from another that reached the same place.
     */
    public function signature(): string
    {
        $needs = array_keys($this->needs);
        sort($needs);

        return implode(',', $needs) . '#' . implode(',', array_map(
            static fn (SliceStep $step): string => $step->signature(),
            $this->steps,
        ));
    }

    /**
     * Several paths gathered into one, when there are more than it pays to keep apart.
     *
     * The paths are kept as alternatives of a single step rather than joined
     * value by value, so running it still runs each of them; what is given up is
     * only keeping them apart from the paths that come before them.
     *
     * @param non-empty-list<self> $paths
     */
    public static function gather(array $paths): self
    {
        $needs = [];
        $alternatives = [];
        $truncated = false;
        $exhausted = false;
        foreach ($paths as $path) {
            $needs += $path->needs;
            $alternatives[] = $path->forward();
            $truncated = $truncated || $path->truncated;
            $exhausted = $exhausted || $path->exhausted;
        }

        return new self([SliceStep::either($alternatives)], $needs, $truncated, $exhausted);
    }
}
