<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\OverrideRows as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\TableOverrides::class)]
final class OverrideRowsTest extends TestCase
{
    public function testAsRowsDistinguishesColumnsFromRowLists(): void
    {
        $rows = new Subject();
        self::assertSame([['id' => 1], ['id' => 2]], $rows->asRows([['id' => 1], ['id' => 2]]));
        self::assertNull($rows->asRows(['id' => 1]));
        self::assertNull($rows->asRows(['a', 'b']));
        self::assertSame([], $rows->asRows([]));
    }
}
