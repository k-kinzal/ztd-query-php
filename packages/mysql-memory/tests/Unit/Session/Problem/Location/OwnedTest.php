<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Problem\Location\Owned;
use MySqlMemory\Session\Problem\Locations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion;

#[CoversClass(Owned::class)]
#[Medium]
final class OwnedTest extends TestCase
{
    public function testLocatedConversionFollowsItsOperandButPrecedesTheNextExpression(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT CAST(1 AT TIME ZONE 'bad' AS DATETIME), missing");
        $locator = (new Locator())->statement($operation->statement);
        [$names] = (new Locations())->names($operation, $locator, [], $operation->facts->diagnostics);
        $located = (new Owned())->located($locator, $operation->facts->diagnostics, $names);
        $first = Locations::first($located);

        self::assertNotNull($first);
        self::assertInstanceOf(TimeZoneConversion::class, $first[0]);
        self::assertCount(2, $located);
    }
}
