<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

/**
 * The execution paths reaching a point in a function body, kept apart.
 *
 * A branch does not merge what its arms leave behind; it forks. Keeping the
 * arms apart is what preserves the correspondence between the values one arm
 * assigns: a branch that sets both a table and a column produces the two
 * statements it can produce, not the four that pairing the values
 * independently would suggest.
 *
 * Paths are bounded. Past the bound they are joined into one, which loses the
 * correspondence and is recorded as such rather than passed off as certainty.
 *
 * @visibility root
 */
final class PathSet
{
    /**
     * How many paths are kept apart before they are joined into one.
     */
    public const MAX_PATHS = 8;

    /**
     * @var list<Environment>
     */
    private array $environments;

    private bool $joined;

    /**
     * @param list<Environment> $environments The paths, each with its own bindings
     * @param bool $joined Whether paths were merged away to stay within the bound
     */
    public function __construct(array $environments = [], bool $joined = false)
    {
        $this->environments = $environments === [] ? [new Environment()] : $environments;
        $this->joined = $joined;
    }

    /**
     * One path with the given bindings.
     */
    public static function of(Environment $environment): self
    {
        return new self([$environment]);
    }

    /**
     * The paths, each with its own bindings.
     *
     * @return list<Environment>
     */
    public function environments(): array
    {
        return $this->environments;
    }

    /**
     * How many paths are being kept apart.
     */
    public function count(): int
    {
        return count($this->environments);
    }

    /**
     * Whether paths were merged away, so the bindings of different paths may now be mixed.
     */
    public function isJoined(): bool
    {
        return $this->joined;
    }

    /**
     * An independent copy, so a branch can be walked without disturbing the caller.
     */
    public function fork(): self
    {
        $copies = [];
        foreach ($this->environments as $environment) {
            $copies[] = $environment->copy();
        }

        return new self($copies, $this->joined);
    }

    /**
     * The paths of both, bounded.
     */
    public function merge(self $other): self
    {
        return (new self(
            array_merge($this->environments, $other->environments),
            $this->joined || $other->joined,
        ))->bounded();
    }

    /**
     * The same paths, joined into one when there are more of them than the bound allows.
     */
    public function bounded(): self
    {
        if (count($this->environments) <= self::MAX_PATHS) {
            return $this;
        }

        return new self([$this->join()], true);
    }

    /**
     * One environment holding what any path may leave behind.
     */
    public function join(): Environment
    {
        $joined = $this->environments[0];
        foreach ($this->environments as $environment) {
            $joined = $joined->join($environment);
        }

        return $joined;
    }

    /**
     * Takes on the paths of another set, in place.
     */
    public function becomeFrom(self $other): void
    {
        $this->environments = $other->environments;
        $this->joined = $this->joined || $other->joined;
    }
}
