<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\Value\Term;
use WeakMap;

/**
 * Bounded immutable node results; eviction cannot mutate caller-owned graphs.
 * @visibility root
 */
final class Cache
{
    /**
     * @var array<string, Term>
     */
    private array $values = [];

    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly int $capacity = 2048)
    {
    }

    /**
     * Finds a retained immutable dependency result.
     */
    public function get(string $key): ?Term
    {
        return $this->values[$key] ?? null;
    }

    /**
     * Retains a dependency result within the configured capacity.
     */
    public function put(string $key, Term $value): void
    {
        if ($this->capacity === 0 || !$this->reusable($value)) {
            return;
        }
        unset($this->values[$key]);
        $this->values[$key] = $value;
        if (count($this->values) > $this->capacity) {
            array_shift($this->values);
        }
    }

    /**
     * Rejects incomplete expansions and terms requiring a query-local frame.
     */
    public function reusable(Term $value): bool
    {
        $pending = [$value];
        $seen = new WeakMap();
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            if (in_array($node->kind, ['deferred', 'recursive', 'closure', 'object'], true) || in_array($node->attributes['reason'] ?? '', ['ENUMERATION_LIMIT', 'CYCLE', 'ITERATION_LIMIT'], true)) {
                return false;
            }
            array_push($pending, ...array_values($node->operands));
        }
        return true;
    }

    /**
     * Releases references owned by this cache.
     */
    public function clear(): void
    {
        $this->values = [];
    }

    /**
     * Reports the number of retained dependency results.
     */
    public function count(): int
    {
        return count($this->values);
    }
}
