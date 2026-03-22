<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final class RewriteProgram
{
    /**
     * @param list<RewriteStep> $steps
     */
    public function __construct(
        public readonly array $steps,
    ) {
        if ($this->steps === []) {
            throw new InvalidArgumentException('Rewrite program must contain at least one step.');
        }
    }

    /**
     * @return list<string>
     */
    public function stepIds(): array
    {
        return array_map(
            static fn (RewriteStep $step): string => $step->id,
            $this->steps,
        );
    }
}
