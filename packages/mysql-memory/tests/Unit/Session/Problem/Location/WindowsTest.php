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
        $operation = (new Instance())->connect()->analyze('SELECT (SELECT MIN(1) OVER inner_missing), MAX(2) OVER outer_missing');
        $locator = (new Locator())->statement($operation->statement);
        $located = (new Windows())->located($locator, $operation->facts->diagnostics, []);
        $first = Locations::first($located);

        self::assertCount(2, $located);
        self::assertNotNull($first);
        self::assertSame($operation->facts->diagnostics[0], $first[0]);
    }
}
