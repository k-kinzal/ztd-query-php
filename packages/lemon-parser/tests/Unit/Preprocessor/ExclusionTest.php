<?php

declare(strict_types=1);

namespace Tests\Unit\Preprocessor;

use LemonParser\Preprocessor\Exclusion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Exclusion::class)]
#[Small]
final class ExclusionTest extends TestCase
{
    public function testDepth(): void
    {
        $exclusion = new Exclusion();

        self::assertSame(0, $exclusion->depth());
        $exclusion->enter(10, 2);
        self::assertSame(1, $exclusion->depth());
    }

    public function testStart(): void
    {
        $exclusion = new Exclusion();
        $exclusion->enter(10, 2);

        self::assertSame(10, $exclusion->start());
    }

    public function testStartLine(): void
    {
        $exclusion = new Exclusion();
        $exclusion->enter(10, 2);

        self::assertSame(2, $exclusion->startLine());
    }

    public function testEnter(): void
    {
        $exclusion = new Exclusion();
        $exclusion->enter(10, 2);
        $exclusion->nest();

        $exclusion->enter(20, 5);

        self::assertSame([1, 20, 5], [$exclusion->depth(), $exclusion->start(), $exclusion->startLine()]);
    }

    public function testNest(): void
    {
        $exclusion = new Exclusion();
        $exclusion->enter(10, 2);

        $exclusion->nest();

        self::assertSame(2, $exclusion->depth());
    }

    public function testLeave(): void
    {
        $exclusion = new Exclusion();
        $exclusion->enter(10, 2);
        $exclusion->nest();

        self::assertFalse($exclusion->leave());
        self::assertTrue($exclusion->leave());
        self::assertSame(0, $exclusion->depth());
    }
}
