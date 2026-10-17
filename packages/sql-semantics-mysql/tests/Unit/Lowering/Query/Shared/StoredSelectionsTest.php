<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredSelections;

#[CoversClass(StoredSelections::class)]
#[Medium]
final class StoredSelectionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.4.7', 'mysql-9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testReadPreservesOrdinarySelectionsAndDeferredOperandRoles(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        self::assertSame('CREATE PROCEDURE p() SELECT 1 AS x FROM DUAL', $semantics->analyze('CREATE PROCEDURE p() SELECT 1 AS x FROM DUAL')->toString());
        self::assertSame('CREATE PROCEDURE p() SELECT (1, 2)', $semantics->analyze('CREATE PROCEDURE p() SELECT (1,2)')->toString());
        self::assertSame('CREATE PROCEDURE p() SELECT 1 ORDER BY 1', $semantics->analyze('CREATE PROCEDURE p() SELECT 1 ORDER BY 1')->toString());
    }

    public function testFrontierPreservesClausesOwnedByOtherQueryRules(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1 LIMIT 1', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 LIMIT 1')->toString());
    }

    public function testItemsKeepsTheScalarOccurrenceAndItsObservableLayout(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1+1, (2, 3) AS pair FROM t', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1+1, (2,3) AS pair FROM t')->toString());
    }

    public function testPredicateRetainsItsRowShape(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1 FROM DUAL WHERE (2, 3)', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 FROM DUAL WHERE (2,3)')->toString());
    }
}
