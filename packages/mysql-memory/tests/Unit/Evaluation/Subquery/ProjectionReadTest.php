<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Evaluation\Subquery\ProjectionRead;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectionRead::class)]
#[Small]
final class ProjectionReadTest extends TestCase
{
    public function testKeepsTheObservedNullStateSeparateFromTheRetainedScalar(): void
    {
        $initial = new ProjectionRead(null, false);
        $cleared = new ProjectionRead('2020-01-02', true);

        self::assertSame([null, false], [$initial->value, $initial->isNull]);
        self::assertSame(['2020-01-02', true], [$cleared->value, $cleared->isNull]);
        self::assertTrue((new ProjectionRead(null))->isNull);
        self::assertFalse((new ProjectionRead(0))->isNull);
    }
}
