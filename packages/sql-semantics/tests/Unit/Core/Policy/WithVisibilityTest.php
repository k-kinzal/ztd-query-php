<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Policy\WithVisibility;

#[CoversClass(WithVisibility::class)]
#[Small]
final class WithVisibilityTest extends TestCase
{
    public function testVisibleSelectsTheNamesTheBodyAtAPositionCanSee(): void
    {
        $names = ['a', 'b', 'c'];
        self::assertSame(['a'], WithVisibility::Preceding->visible($names, 1));
        self::assertSame([], WithVisibility::Preceding->visible($names, 0));
        self::assertSame(['a', 'b'], WithVisibility::PrecedingAndItself->visible($names, 1));
        self::assertSame(['a'], WithVisibility::PrecedingAndItself->visible($names, 0));
        self::assertSame($names, WithVisibility::All->visible($names, 0));
    }
}
