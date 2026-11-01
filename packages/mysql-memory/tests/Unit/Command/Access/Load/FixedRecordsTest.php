<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access\Load;

use MySqlMemory\Command\Access\Load\FixedRecords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixedRecords::class)]
#[Small]
final class FixedRecordsTest extends TestCase
{
    public function testReadPreservesShortAndSurplusRecords(): void
    {
        self::assertSame([['001', 'ab'], ['002'], [], ['003', 'abc', 'extra']], (new FixedRecords())->read("001ab \n002\n\n003abcextra\n", [], [3, 3]));
    }
}
