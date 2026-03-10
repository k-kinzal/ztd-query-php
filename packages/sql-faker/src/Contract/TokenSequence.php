<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final class TokenSequence
{
    /**
     * @var list<string>
     */
    public readonly array $tokens;

    /**
     * @param array<array-key, mixed> $tokens
     */
    public function __construct(array $tokens)
    {
        if (!array_is_list($tokens)) {
            throw new InvalidArgumentException('Token sequence must be a list.');
        }

        foreach ($tokens as $token) {
            if (!is_string($token) || $token === '') {
                throw new InvalidArgumentException('Token sequence entries must be non-empty strings.');
            }
        }

        /** @var list<string> $tokens */
        $this->tokens = $tokens;
    }
}
