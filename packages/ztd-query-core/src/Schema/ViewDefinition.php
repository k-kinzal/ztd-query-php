<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * One view: the statement it stands for, and what that reaches.
 *
 * Shadowing a view means shadowing everything its statement reads, so the
 * tables it depends on are part of what a view is here.
 */
final class ViewDefinition
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        public readonly string $query,
        public readonly array $dependencies,
    ) {
    }
}
