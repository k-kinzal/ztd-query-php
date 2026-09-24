<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Instances;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Administration\MasterKeyScope;
use SqlSemantics\Model\Configuration\Administration\TlsChannel;
use SqlSemantics\Model\Statement\Server\Administration\AlterRedoLogStatement;
use SqlSemantics\Model\Statement\Server\Administration\ReloadKeyringStatement;
use SqlSemantics\Model\Statement\Server\Administration\ReloadTlsStatement;
use SqlSemantics\Model\Statement\Server\Administration\RotateMasterKeyStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Instances::class)]
#[Medium]
final class InstancesTest extends TestCase
{
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsEveryActionOnCurrentReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $rotate = $binder->bind("ALTER INSTANCE ROTATE 'binlog' MASTER KEY");
        $redo = $binder->bind('ALTER INSTANCE DISABLE innodb REDO_LOG');
        self::assertInstanceOf(RotateMasterKeyStatement::class, $rotate);
        self::assertSame(MasterKeyScope::BinaryLog, $rotate->scope);
        self::assertInstanceOf(AlterRedoLogStatement::class, $redo);
        self::assertFalse($redo->enabled);
        self::assertInstanceOf(ReloadKeyringStatement::class, $binder->bind('ALTER INSTANCE RELOAD KEYRING'));
        self::assertSame($redo->toString(), $binder->bind($redo->toString())->toString());
    }

    public function testBindReadsTheLegacyRotation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER INSTANCE ROTATE INNODB MASTER KEY');
        self::assertInstanceOf(RotateMasterKeyStatement::class, $statement);
        self::assertSame('ALTER INSTANCE ROTATE INNODB MASTER KEY', $statement->toString());
    }

    public function testBindDiagnosesAnUnknownRedoLogTarget(): void
    {
        $this->expectExceptionObject(new InvalidSql(InputViolation::InstanceAction, new Node('alter_instance_action', 0, [])));
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE ENABLE innodb undo_log');
    }

    public function testScopeDiagnosesBinaryLogKeysOnLegacyReleases(): void
    {
        self::assertSame(MasterKeyScope::InnoDb, Instances::scope('INNODB', 50744, new Node('alter_instance_action', 0, [])));
        $this->expectException(InvalidSql::class);
        Instances::scope('BINLOG', 50744, new Node('alter_instance_action', 0, []));
    }

    public function testTlsReadsTheChannelAndDiagnosesAnUnknownOne(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('ALTER INSTANCE RELOAD TLS FOR CHANNEL mysql_main NO ROLLBACK ON ERROR');
        self::assertInstanceOf(ReloadTlsStatement::class, $statement);
        self::assertSame(TlsChannel::Main, $statement->channel);
        self::assertSame('ALTER INSTANCE RELOAD TLS NO ROLLBACK ON ERROR', $statement->toString());
        $this->expectException(InvalidSql::class);
        $binder->bind('ALTER INSTANCE RELOAD TLS FOR CHANNEL replication');
    }
}
