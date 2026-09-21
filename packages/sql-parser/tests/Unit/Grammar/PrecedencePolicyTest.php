<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\PrecedencePolicy;

#[CoversClass(PrecedencePolicy::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class PrecedencePolicyTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['LastTerminal', 'FirstRankedTerminal'], array_map(static fn (PrecedencePolicy $case): string => $case->name, PrecedencePolicy::cases()));
    }
}
