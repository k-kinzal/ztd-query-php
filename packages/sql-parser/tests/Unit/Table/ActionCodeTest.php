<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Table\ActionCode;

#[CoversClass(ActionCode::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class ActionCodeTest extends TestCase
{
    public function testShift(): void
    {
        self::assertSame(12, ActionCode::shift(12));
    }

    public function testReduce(): void
    {
        self::assertSame(-1, ActionCode::reduce(0));
        self::assertSame(-6, ActionCode::reduce(5));
        self::assertSame(ActionCode::ACCEPT, ActionCode::reduce(0));
    }

    public function testIsShift(): void
    {
        self::assertTrue(ActionCode::isShift(0));
        self::assertFalse(ActionCode::isShift(-1));
        self::assertFalse(ActionCode::isShift(ActionCode::ERROR));
    }

    public function testIsReduce(): void
    {
        self::assertTrue(ActionCode::isReduce(-3));
        self::assertFalse(ActionCode::isReduce(3));
        self::assertFalse(ActionCode::isReduce(ActionCode::ERROR));
    }

    public function testRule(): void
    {
        self::assertSame(5, ActionCode::rule(ActionCode::reduce(5)));
    }
}
