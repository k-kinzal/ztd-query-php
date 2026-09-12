<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Value\TypeAffinity as Subject;

#[CoversClass(Subject::class)]
final class TypeAffinityTest extends TestCase
{
    public function testDetermineAffinityInteger(): void
    {
        self::assertSame('INTEGER', (new Subject())->determineAffinity('INT'));
    }

    public function testDetermineAffinityText(): void
    {
        self::assertSame('TEXT', (new Subject())->determineAffinity('VARCHAR'));
    }

    public function testDetermineAffinityBlob(): void
    {
        self::assertSame('BLOB', (new Subject())->determineAffinity('BLOB'));
    }

    public function testDetermineAffinityEmpty(): void
    {
        self::assertSame('BLOB', (new Subject())->determineAffinity(''));
    }

    public function testDetermineAffinityReal(): void
    {
        self::assertSame('REAL', (new Subject())->determineAffinity('DOUBLE'));
    }

    public function testDetermineAffinityNumeric(): void
    {
        self::assertSame('NUMERIC', (new Subject())->determineAffinity('DECIMAL'));
    }

    public function testDetermineAffinityPrioritizesIntegerSubstring(): void
    {
        self::assertSame('INTEGER', (new Subject())->determineAffinity('FLOATING POINT'));
    }
}
