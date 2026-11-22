<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Storage\TablespaceRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TablespaceRule::class)]
#[Medium]
final class TablespaceRuleTest extends TestCase
{
    public function testStatementLowersCreateOf80(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' USE LOGFILE GROUP g ENGINE `ndb`", (new Inspection())->inspect("create tablespace ts add datafile 'f' use logfile group g engine ndb")->toString());
    }

    public function testChangeLowersTheAccessMode(): void
    {
        self::assertSame('ALTER TABLESPACE ts NOT ACCESSIBLE', (new Inspection('mysql-5.6.51'))->inspect('alter tablespace ts not accessible')->toString());
    }

    public function testNameLowersTheTablespaceName(): void
    {
        self::assertSame('DROP TABLESPACE `t s`', (new Inspection('mysql-5.7.44'))->inspect('drop tablespace `t s`')->toString());
    }

    public function testDatafileLowersTheFile(): void
    {
        self::assertSame("ALTER TABLESPACE ts ADD DATAFILE 'f'", (new Inspection())->inspect("alter tablespace ts add datafile 'f'")->toString());
    }

    public function testOptionalDatafileLowersAnAbsentFile(): void
    {
        self::assertSame('CREATE TABLESPACE ts', (new Inspection())->inspect('create tablespace ts')->toString());
    }

    public function testGroupLowersUseLogfileGroup(): void
    {
        self::assertSame("CREATE TABLESPACE ts ADD DATAFILE 'f' USE LOGFILE GROUP g", (new Inspection('mysql-5.6.51'))->inspect("create tablespace ts add datafile 'f' use logfile group g")->toString());
    }

    public function testAccessLowersReadOnly(): void
    {
        self::assertSame('ALTER TABLESPACE ts READ_ONLY', (new Inspection('mysql-5.7.44'))->inspect('alter tablespace ts read_only')->toString());
    }

    public function testActiveLowersInactive(): void
    {
        self::assertSame('ALTER UNDO TABLESPACE u SET INACTIVE', (new Inspection())->inspect('alter undo tablespace u set inactive')->toString());
    }

    public function testOptionsLowersTheList(): void
    {
        self::assertSame("ALTER TABLESPACE ts CHANGE DATAFILE 'f' INITIAL_SIZE 1 MAX_SIZE 2", (new Inspection('mysql-5.6.51'))->inspect("alter tablespace ts change datafile 'f' initial_size 1, max_size 2")->toString());
    }
}
