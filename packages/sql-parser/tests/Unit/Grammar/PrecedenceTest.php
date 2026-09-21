<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Precedence;

#[CoversClass(Precedence::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class PrecedenceTest extends TestCase
{
    public function testLevelAndAssociativityAreKept(): void
    {
        $precedence = new Precedence(3, Associativity::Right);

        self::assertSame(3, $precedence->level);
        self::assertSame(Associativity::Right, $precedence->associativity);
    }
}
