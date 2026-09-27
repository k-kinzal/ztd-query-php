<?php

declare(strict_types=1);

namespace Deriver\Memory;

use Deriver\Value\Term;

/**
 * Retains the next live foreach buckets across deletion, insertion, and array replacement.
 * @visibility root
 */
final class LiveArray
{
    /**
     * @param Location $location Pinned array reference cell
     * @param list<int|string> $remaining Buckets not yet visited by this cursor
     * @param int|string|null $current Most recently fetched bucket
     */
    public function __construct(public readonly Location $location, public readonly array $remaining, public readonly int|string|null $current = null)
    {
    }

    /**
     * Advances to the next remaining bucket without depending on a compacted array index.
     * @return self Independently writable cursor state
     */
    public function advance(): self
    {
        $remaining = $this->remaining;
        $current = array_shift($remaining);
        return new self($this->location, $remaining, $current);
    }

    /**
     * Preserves bucket lifetime through in-place changes, restarting only for replacement.
     * @param Term $before Array before the storage operation
     * @param Term $after Array after the storage operation
     * @param bool $replacement Whether the entire referenced array was reassigned
     * @return self Cursor after the storage operation
     */
    public function changed(Term $before, Term $after, bool $replacement): self
    {
        if ($before === $after) {
            return $this;
        }
        if ($replacement || $after->kind !== 'array') {
            return new self($this->location, $after->kind === 'array' ? array_keys($after->operands) : [], $this->current);
        }
        $remaining = [];
        foreach ($this->remaining as $key) {
            if (isset($after->operands[$key])) {
                $remaining[] = $key;
            }
        }
        foreach (array_keys($after->operands) as $key) {
            if (!array_key_exists($key, $before->operands)) {
                $remaining[] = $key;
            }
        }
        return new self($this->location, $remaining, $this->current);
    }
}
