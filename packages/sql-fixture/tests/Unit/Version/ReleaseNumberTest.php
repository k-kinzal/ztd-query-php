<?php

declare(strict_types=1);

namespace Tests\Unit\Version;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Version\ReleaseNumber as Subject;

#[CoversClass(Subject::class)]
final class ReleaseNumberTest extends TestCase
{
    public function testFromReportedDropsTheSuffix(): void
    {
        self::assertSame('8.0.36', Subject::fromReported('8.0.36-0ubuntu0.22.04.1')?->number);
        self::assertSame('17.2', Subject::fromReported('17.2 (Debian 17.2-1.pgdg120+1)')?->number);
        self::assertNull(Subject::fromReported('unknown'));
    }

    public function testIdPadsMissingParts(): void
    {
        self::assertSame(80036, (new Subject('8.0.36'))->id());
        self::assertSame(170200, (new Subject('17.2'))->id());
        self::assertSame(30000, (new Subject('3'))->id());
    }

    public function testSeriesKeepsEveryNumberButTheLast(): void
    {
        self::assertSame('8.0', (new Subject('8.0.36'))->series());
        self::assertSame('17', (new Subject('17.2'))->series());
        self::assertSame('3', (new Subject('3'))->series());
    }
}
