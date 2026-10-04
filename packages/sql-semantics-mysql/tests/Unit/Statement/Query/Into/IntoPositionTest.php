<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Into;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;

#[CoversClass(IntoPosition::class)]
#[Small]
final class IntoPositionTest extends TestCase
{
    public function testCasesNameTheThreePositions(): void
    {
        self::assertSame(['AfterItems', 'AfterQuery', 'AfterLocking'], array_column(IntoPosition::cases(), 'name'));
    }
}
