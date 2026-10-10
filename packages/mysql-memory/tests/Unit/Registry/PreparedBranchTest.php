<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\PreparedBranch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreparedBranch::class)]
#[Small]
final class PreparedBranchTest extends TestCase
{
    public function testKeyTellsTheTwoPartsOfTheXidApart(): void
    {
        self::assertNotSame(PreparedBranch::key(1, 'ab', 'c'), PreparedBranch::key(1, 'a', 'bc'));
    }

    public function testKeyTellsFormatsApart(): void
    {
        self::assertNotSame(PreparedBranch::key(1, 'a', ''), PreparedBranch::key(2, 'a', ''));
    }
}
