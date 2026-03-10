<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final class TerminalSequence
{
    /**
     * @var list<string>
     */
    public readonly array $terminals;

    /**
     * @param array<array-key, mixed> $terminals
     */
    public function __construct(array $terminals)
    {
        if (!array_is_list($terminals)) {
            throw new InvalidArgumentException('Terminal sequence must be a list.');
        }

        foreach ($terminals as $terminal) {
            if (!is_string($terminal) || $terminal === '') {
                throw new InvalidArgumentException('Terminal sequence entries must be non-empty strings.');
            }
        }

        /** @var list<string> $terminals */
        $this->terminals = $terminals;
    }
}
