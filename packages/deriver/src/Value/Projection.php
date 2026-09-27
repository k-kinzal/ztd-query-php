<?php

declare(strict_types=1);

namespace Deriver\Value;

/**
 * A path into an array, object state, or domain record.
 *
 * @visibility public
 * @example Selecting one array entry
 *     (new \Deriver\Value\Projection(['options', 'limit']))->path // => ['options', 'limit']
 */
final class Projection
{
    /**
     * @param list<int|string> $path Ordered keys; an empty path selects the whole value
     * @param string|null $slot Abstract receiver state slot selected before the ordinary path
     */
    public function __construct(public readonly array $path = [], public readonly ?string $slot = null)
    {
    }
    /**
     * Selects an abstract receiver slot independently of PHP property names.
     * @param string $id Registered state slot identity
     * @param list<int|string> $path Optional path inside the slot
     * @return self Slot projection with explicit storage semantics
     */
    public static function stateSlot(string $id, array $path = []): self
    {
        return new self($path, $id);
    }
}
