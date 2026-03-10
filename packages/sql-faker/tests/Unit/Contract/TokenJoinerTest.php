<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TokenJoiner;
use SqlFaker\Contract\TokenSequence;

#[CoversNothing]
final class TokenJoinerTest extends TestCase
{
    public function testTokenJoinerContractCanBeImplemented(): void
    {
        $joiner = new class () implements TokenJoiner {
            public function join(TokenSequence $tokens): string
            {
                return implode(' ', $tokens->tokens);
            }
        };

        self::assertSame('SELECT 1', $joiner->join(new TokenSequence(['SELECT', '1'])));
    }
}
