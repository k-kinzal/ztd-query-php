<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\InstanceRule;

#[CoversClass(InstanceRule::class)]
#[Medium]
final class InstanceRuleTest extends TestCase
{
    public function testStatementLowersEveryInstanceStatement(): void
    {
        self::assertSame('SHUTDOWN', (new Semantics(Dialect::MySql))->analyze('shutdown')->toString());
    }

    public function testScopeLowersQuery(): void
    {
        self::assertSame('KILL QUERY 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('kill query 1')->toString());
    }

    public function testLocalLowersTheDirectory(): void
    {
        self::assertSame("CLONE LOCAL DATA DIRECTORY '/d'", (new Semantics(Dialect::MySql))->analyze("clone local data directory = '/d'")->toString());
    }

    public function testRemoteRejectsASpaceAroundTheColon(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql))->analyze("CLONE INSTANCE FROM u@h :1 IDENTIFIED BY 'p'");
    }

    public function testDirectoryLowersDirectoryAndSsl(): void
    {
        self::assertSame("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p' DATA DIRECTORY '/d' REQUIRE NO SSL", (new Semantics(Dialect::MySql))->analyze("clone instance from u@h:1 identified by 'p' data directory '/d' require no ssl")->toString());
    }

    public function testSslLowersRequireSsl(): void
    {
        self::assertSame("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p' REQUIRE SSL", (new Semantics(Dialect::MySql))->analyze("clone instance from u@h:1 identified by 'p' require ssl")->toString());
    }

    public function testActionLowersReloadTls(): void
    {
        self::assertSame('ALTER INSTANCE RELOAD TLS FOR CHANNEL c', (new Semantics(Dialect::MySql))->analyze('alter instance reload tls for channel c')->toString());
    }

    public function testRotateRejectsBinlogIn57(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER INSTANCE ROTATE BINLOG MASTER KEY');
    }

    public function testRedoRejectsOtherNames(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql))->analyze('ALTER INSTANCE ENABLE innodb undo_log');
    }
}
