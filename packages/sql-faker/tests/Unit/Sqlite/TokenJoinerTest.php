<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\Sqlite\TokenJoiner;

#[CoversNothing]
final class TokenJoinerTest extends TestCase
{
    public function testJoinAppliesSqliteSpacingRules(): void
    {
        $joiner = new TokenJoiner();

        self::assertSame('json->*', $joiner->join(new TokenSequence(['json', '->', '*'])));
    }
}
