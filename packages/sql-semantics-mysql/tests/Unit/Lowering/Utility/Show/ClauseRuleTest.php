<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ClauseRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ClauseRule::class)]
#[Medium]
final class ClauseRuleTest extends TestCase
{
    public function testFilterLowersLikeAndWhere(): void
    {
        self::assertSame("SHOW STATUS LIKE 'a'", (new Inspection('mysql-5.6.51'))->inspect("show status like 'a'")->toString());
        self::assertSame('SHOW VARIABLES WHERE 1', (new Inspection('mysql-5.7.44'))->inspect('show variables where 1')->toString());
    }

    public function testWhereLowersTheQueryClause(): void
    {
        self::assertSame('SHOW INDEXES FROM t WHERE 1', (new Inspection())->inspect('show keys from t where 1')->toString());
    }

    public function testDatabaseLowersTheClause(): void
    {
        self::assertSame('SHOW EVENTS FROM db', (new Inspection())->inspect('show events in db')->toString());
    }

    public function testPrepositionAcceptsFromAndIn(): void
    {
        self::assertSame('SHOW COLUMNS FROM t FROM db', (new Inspection())->inspect('show columns in t in db')->toString());
    }

    public function testListingLowersFullAndExtended(): void
    {
        self::assertSame('SHOW EXTENDED COLUMNS FROM t', (new Inspection())->inspect('show extended columns from t')->toString());
    }

    public function testExtendedLowersTheKeyword(): void
    {
        self::assertSame('SHOW EXTENDED INDEXES FROM t', (new Inspection())->inspect('show extended keys from t')->toString());
    }

    public function testScopeLowersTheKeywords(): void
    {
        self::assertSame('SHOW SESSION STATUS', (new Inspection())->inspect('show local status')->toString());
    }

    public function testEngineLowersAll(): void
    {
        self::assertSame('SHOW ENGINE ALL LOGS', (new Inspection())->inspect('show engine all logs')->toString());
    }

    public function testFileLowersIn(): void
    {
        self::assertSame("SHOW BINLOG EVENTS IN 'b'", (new Inspection())->inspect("show binlog events in 'b'")->toString());
    }

    public function testPositionLowersFrom(): void
    {
        self::assertSame('SHOW RELAYLOG EVENTS FROM 4', (new Inspection())->inspect('show relaylog events from 4')->toString());
    }

    public function testSectionsLowersTheList(): void
    {
        self::assertSame('SHOW PROFILE BLOCK IO, CONTEXT SWITCHES, PAGE FAULTS, SWAPS', (new Inspection())->inspect('show profile block io, context switches, page faults, swaps')->toString());
    }

    public function testQueryLowersTheNumber(): void
    {
        self::assertSame('SHOW PROFILE FOR QUERY 3', (new Inspection('mysql-5.6.51'))->inspect('show profile for query 3')->toString());
    }
}
