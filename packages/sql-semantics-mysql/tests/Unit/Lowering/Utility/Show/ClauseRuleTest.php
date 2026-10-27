<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ClauseRule;

#[CoversClass(ClauseRule::class)]
#[Medium]
final class ClauseRuleTest extends TestCase
{
    public function testFilterLowersLikeAndWhere(): void
    {
        self::assertSame("SHOW STATUS LIKE 'a'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("show status like 'a'")->toString());
        self::assertSame('SHOW VARIABLES WHERE 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show variables where 1')->toString());
    }

    public function testWhereLowersTheQueryClause(): void
    {
        self::assertSame('SHOW INDEXES FROM t WHERE 1', (new Semantics(Dialect::MySql))->analyze('show keys from t where 1')->toString());
    }

    public function testDatabaseLowersTheClause(): void
    {
        self::assertSame('SHOW EVENTS FROM db', (new Semantics(Dialect::MySql))->analyze('show events in db')->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDatabaseOperands(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['TABLES', 'TRIGGERS', 'EVENTS', 'TABLE STATUS', 'COLUMNS FROM t', 'KEYS FROM t'] as $form) {
                yield $release . ' ' . $form => [$release, 'SHOW ' . $form . ' FROM ``'];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDatabaseOperands')]
    public function testDatabaseRefusesEmptyDatabaseIdentities(string $release, string $sql): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Incorrect database name ''");
        (new Semantics(Dialect::MySql, 'mysql-' . $release))->analyze($sql);
    }

    public function testDatabaseAllowsAnEmptyOpenTableFilter(): void
    {
        self::assertSame('SHOW OPEN TABLES FROM ``', (new Semantics(Dialect::MySql))->analyze('SHOW OPEN TABLES FROM ``')->toString());
    }

    public function testPrepositionAcceptsFromAndIn(): void
    {
        self::assertSame('SHOW COLUMNS FROM t FROM db', (new Semantics(Dialect::MySql))->analyze('show columns in t in db')->toString());
    }

    public function testListingLowersFullAndExtended(): void
    {
        self::assertSame('SHOW EXTENDED COLUMNS FROM t', (new Semantics(Dialect::MySql))->analyze('show extended columns from t')->toString());
    }

    public function testExtendedLowersTheKeyword(): void
    {
        self::assertSame('SHOW EXTENDED INDEXES FROM t', (new Semantics(Dialect::MySql))->analyze('show extended keys from t')->toString());
    }

    public function testScopeLowersTheKeywords(): void
    {
        self::assertSame('SHOW SESSION STATUS', (new Semantics(Dialect::MySql))->analyze('show local status')->toString());
    }

    public function testEngineLowersAll(): void
    {
        self::assertSame('SHOW ENGINE ALL LOGS', (new Semantics(Dialect::MySql))->analyze('show engine all logs')->toString());
    }

    public function testFileLowersIn(): void
    {
        self::assertSame("SHOW BINLOG EVENTS IN 'b'", (new Semantics(Dialect::MySql))->analyze("show binlog events in 'b'")->toString());
    }

    public function testPositionLowersFrom(): void
    {
        self::assertSame('SHOW RELAYLOG EVENTS FROM 4', (new Semantics(Dialect::MySql))->analyze('show relaylog events from 4')->toString());
    }

    public function testSectionsLowersTheList(): void
    {
        self::assertSame('SHOW PROFILE BLOCK IO, CONTEXT SWITCHES, PAGE FAULTS, SWAPS', (new Semantics(Dialect::MySql))->analyze('show profile block io, context switches, page faults, swaps')->toString());
    }

    public function testQueryLowersTheNumber(): void
    {
        self::assertSame('SHOW PROFILE FOR QUERY 3', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show profile for query 3')->toString());
    }
    public function testInspectionRetainsTheOwnerWhenItsScalarFilterIsRefused(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('SHOW TABLE STATUS FROM d WHERE ROW(1,2) LIKE 1');
            self::fail('A non-scalar SHOW condition must retain its refused role.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter::class, $failure->problem);
            self::assertSame('d', $failure->problem->header->database?->value);
        }
    }


    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerFilteredInspections(): iterable
    {
        foreach (['5.7.44', '8.4.7'] as $release) {
            foreach (['DATABASES', 'TABLES', 'TABLE STATUS', 'TRIGGERS', 'COLUMNS FROM t', 'KEYS FROM t', 'EVENTS', 'PROCEDURE STATUS', 'FUNCTION STATUS', 'OPEN TABLES', 'STATUS', 'VARIABLES', 'CHARACTER SET', 'COLLATION'] as $header) {
                yield $release . ':' . $header => [$release, 'SHOW ' . $header];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerFilteredInspections')]
    public function testInspectionRetainsEveryFilterOwner(string $release, string $header): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-' . $release);
        $expected = $semantics->analyze($header);
        try {
            $semantics->analyze($header . ' WHERE (1,2)');
            self::fail('A row condition must retain its refused scalar role.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter::class, $failure->problem);
            self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference($expected, $failure->problem->header));
        }
    }
}
