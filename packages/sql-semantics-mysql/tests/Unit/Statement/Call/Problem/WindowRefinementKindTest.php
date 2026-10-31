<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind;

#[CoversClass(WindowRefinementKind::class)]
#[Small]
final class WindowRefinementKindTest extends TestCase
{
    public function testCasesDescribeDistinctInheritanceRelationships(): void
    {
        self::assertSame(['Partition', 'Ordering', 'Frame'], array_column(WindowRefinementKind::cases(), 'name'));
    }
}
