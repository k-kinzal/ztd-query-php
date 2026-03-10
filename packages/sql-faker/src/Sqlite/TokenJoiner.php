<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Contract\TokenJoiner as TokenJoinerContract;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\Grammar\TokenJoiner as GrammarTokenJoiner;

final class TokenJoiner implements TokenJoinerContract
{
    public function join(TokenSequence $tokens): string
    {
        return GrammarTokenJoiner::join($tokens->tokens, [['->', '*'], ['*', '->']]);
    }
}
