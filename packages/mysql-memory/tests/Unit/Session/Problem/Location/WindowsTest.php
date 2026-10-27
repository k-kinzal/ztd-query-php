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
        $operation = (new Instance())->connect()->analyze('CREATE PROCEDURE p() SELECT (SELECT MIN(1) OVER inner_missing), MAX(2) OVER outer_missing');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        $locator = (new Locator())->statement($operation->body);
        $located = (new Windows())->located($locator, $operation->statementInformation()->diagnostics, []);
        $first = Locations::first($located);

        self::assertCount(2, $located);
        self::assertNotNull($first);
        self::assertSame($operation->statementInformation()->diagnostics[0], $first[0]);
    }
    public function testInheritedKeepsTheOriginalNameAndBlockPosition(): void
    {
        $operation = (new Instance())->connect()->analyze('SELECT 1 WINDOW original AS (), w AS (original)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->query);
        $names = (new Windows())->inherited($operation->query, [2]);
        self::assertCount(1, $names);
        self::assertSame('original', $names[0][0]->value);
        self::assertSame([2, 6, PHP_INT_MAX, PHP_INT_MAX], $names[0][1]);
    }

}
