<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface TokenJoiner
{
    public function join(TokenSequence $tokens): string;
}
