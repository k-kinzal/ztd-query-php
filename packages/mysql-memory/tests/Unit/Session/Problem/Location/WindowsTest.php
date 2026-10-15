<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Instance;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Problem\Location\Windows;
use MySqlMemory\Session\Problem\Locations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Windows::class)]
#[Medium]
final class WindowsTest extends TestCase
{
    public function testLocatedFinishesTheNestedBlockBeforeResolvingALaterOuterWindow(): void
    {
        $operation = (new Instance())->connect()->analyze('SELECT (SELECT GROUP_CONCAT(1) OVER ()), GROUP_CONCAT(2) OVER missing');
        $locator = (new Locator())->statement($operation->statement);
        $located = (new Windows())->located($locator, $operation->facts->diagnostics, []);
        $first = Locations::first($located);

        self::assertCount(3, $located);
        self::assertNotNull($first);
        self::assertSame($operation->facts->diagnostics[0], $first[0]);
    }
}
