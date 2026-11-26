<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\ColumnRemoval;

#[CoversClass(ColumnRemoval::class)]
#[Small]
final class ColumnRemovalTest extends TestCase
{
    public function testIndexesEveryProtectedSlotWithinTheHeading(): void
    {
        $slot = 2;
        $removal = new ColumnRemoval(4, [0, &$slot, 0]);
        $slot = 1;

        self::assertSame([0 => true, 2 => true], $removal->protected);
    }
}
