<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\ConflictSummary;

#[CoversClass(ConflictSummary::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class ConflictSummaryTest extends TestCase
{
    public function testIsExpected(): void
    {
        self::assertTrue((new ConflictSummary(0, 0, null))->isExpected());
        self::assertTrue((new ConflictSummary(59, 0, 59))->isExpected());
        self::assertFalse((new ConflictSummary(1, 0, null))->isExpected());
        self::assertFalse((new ConflictSummary(59, 1, 59))->isExpected());
        self::assertFalse((new ConflictSummary(58, 0, 59))->isExpected());
    }
}
