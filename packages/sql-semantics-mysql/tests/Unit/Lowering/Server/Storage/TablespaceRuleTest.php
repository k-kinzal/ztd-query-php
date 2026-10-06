<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Storage\TablespaceRule;

#[CoversClass(TablespaceRule::class)]
#[Medium]
final class TablespaceRuleTest extends TestCase
{
    public function testStatementLowersCreateOf80(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' USE LOGFILE GROUP g ENGINE `ndb`", (new Semantics(Dialect::MySql))->analyze("create tablespace ts add datafile 'f' use logfile group g engine ndb")->toString());
    }

    public function testChangeLowersTheAccessMode(): void
    {
        self::assertSame('ALTER TABLESPACE ts NOT ACCESSIBLE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter tablespace ts not accessible')->toString());
    }

    public function testNameLowersTheTablespaceName(): void
    {
        self::assertSame('DROP TABLESPACE `t s`', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('drop tablespace `t s`')->toString());
    }

    public function testDatafileLowersTheFile(): void
    {
        self::assertSame("ALTER TABLESPACE ts ADD DATAFILE 'f'", (new Semantics(Dialect::MySql))->analyze("alter tablespace ts add datafile 'f'")->toString());
    }

    public function testOptionalDatafileLowersAnAbsentFile(): void
    {
        self::assertSame('CREATE TABLESPACE ts', (new Semantics(Dialect::MySql))->analyze('create tablespace ts')->toString());
    }

    public function testGroupLowersUseLogfileGroup(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' USE LOGFILE GROUP g", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("create tablespace ts add datafile 'f' use logfile group g")->toString());
    }

    public function testAccessLowersReadOnly(): void
    {
        self::assertSame('ALTER TABLESPACE ts READ_ONLY', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('alter tablespace ts read_only')->toString());
    }

    public function testActiveLowersInactive(): void
    {
        self::assertSame('ALTER UNDO TABLESPACE u SET INACTIVE', (new Semantics(Dialect::MySql))->analyze('alter undo tablespace u set inactive')->toString());
    }

    public function testOptionsLowersTheList(): void
    {
        self::assertSame("ALTER TABLESPACE ts CHANGE DATAFILE 'f' INITIAL_SIZE 1 MAX_SIZE 2", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("alter tablespace ts change datafile 'f' initial_size 1, max_size 2")->toString());
    }
}
