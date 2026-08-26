<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

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
