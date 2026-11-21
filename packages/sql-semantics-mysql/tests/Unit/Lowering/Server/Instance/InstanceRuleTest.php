<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\InstanceRule;
use SqlSemantics\Platform\MySql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(InstanceRule::class)]
#[Medium]
final class InstanceRuleTest extends TestCase
{
    public function testStatementLowersEveryInstanceStatement(): void
    {
        self::assertSame('SHUTDOWN', (new Semantics())->inspect('shutdown')->toString());
    }

    public function testScopeLowersQuery(): void
    {
        self::assertSame('KILL QUERY 1', (new Semantics('mysql-5.6.51'))->inspect('kill query 1')->toString());
    }

    public function testLocalLowersTheDirectory(): void
    {
        self::assertSame("CLONE LOCAL DATA DIRECTORY '/d'", (new Semantics())->inspect("clone local data directory = '/d'")->toString());
    }

    public function testRemoteRejectsASpaceAroundTheColon(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics())->inspect("CLONE INSTANCE FROM u@h :1 IDENTIFIED BY 'p'");
    }

    public function testDirectoryLowersDirectoryAndSsl(): void
    {
        self::assertSame("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p' DATA DIRECTORY '/d' REQUIRE NO SSL", (new Semantics())->inspect("clone instance from u@h:1 identified by 'p' data directory '/d' require no ssl")->toString());
    }

    public function testSslLowersRequireSsl(): void
    {
        self::assertSame("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p' REQUIRE SSL", (new Semantics())->inspect("clone instance from u@h:1 identified by 'p' require ssl")->toString());
    }

    public function testActionLowersReloadTls(): void
    {
        self::assertSame('ALTER INSTANCE RELOAD TLS FOR CHANNEL c', (new Semantics())->inspect('alter instance reload tls for channel c')->toString());
    }

    public function testRotateRejectsBinlogIn57(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics('mysql-5.7.44'))->inspect('ALTER INSTANCE ROTATE BINLOG MASTER KEY');
    }

    public function testRedoRejectsOtherNames(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics())->inspect('ALTER INSTANCE ENABLE innodb undo_log');
    }
}
