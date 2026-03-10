<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\PostgreSql\TokenJoiner;

#[CoversNothing]
final class TokenJoinerTest extends TestCase
{
    public function testJoinAppliesPostgreSqlSpacingRules(): void
    {
        $joiner = new TokenJoiner();

        self::assertSame('value::*', $joiner->join(new TokenSequence(['value', '::', '*'])));
    }
}
