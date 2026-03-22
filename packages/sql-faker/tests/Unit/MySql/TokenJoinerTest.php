<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\MySql\TokenJoiner;

#[CoversNothing]
final class TokenJoinerTest extends TestCase
{
    public function testJoinAppliesMySqlSpacingRules(): void
    {
        $joiner = new TokenJoiner();

        self::assertSame('COUNT(*)', $joiner->join(new TokenSequence(['COUNT', '(', '*', ')'])));
    }
}
