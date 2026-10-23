<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Problem\Location\WriteFunctions;
use MySqlMemory\Session\Problem\Locations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(WriteFunctions::class)]
#[Small]
final class WriteFunctionsTest extends TestCase
{
    public function testLocatedRetainsNestedQueryGroupingAndRejectsTheOuterAggregate(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT)');
        $operation = $session->analyze('UPDATE t SET a=(SELECT AVG(a) FROM t) WHERE AVG(a)');
        $locator = (new Locator())->statement($operation->statement);

        $located = (new WriteFunctions())->located($operation->statement, $locator, []);
        $first = Locations::first($located);

        self::assertCount(1, $located);
        self::assertNotNull($first);
        self::assertInstanceOf(SqlError::class, $first[0]);
        self::assertSame(1111, $first[0]->getCode());
        self::assertSame('where clause', $first[1][0]);
    }

    public function testLocatedLeavesQueryGroupingToItsOwnScope(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT AVG(1)');
        $locator = (new Locator())->statement($operation->statement);

        self::assertSame([], (new WriteFunctions())->located($operation->statement, $locator, []));
    }
}
