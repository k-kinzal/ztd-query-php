<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\RelayLogFlush;
use SqlSemantics\Model\Configuration\Administration\ServerFlush;
use SqlSemantics\Model\Statement\Server\Administration\FlushTablesWithReadLockStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Server\FlushCommands;

#[CoversClass(FlushCommands::class)]
#[Medium]
final class FlushCommandsTest extends TestCase
{
    public function testWriteSpellsTheTableFormAndPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH LOCAL TABLE t WITH READ LOCK');
        self::assertInstanceOf(FlushTablesWithReadLockStatement::class, $statement);
        self::assertSame('flush', FlushCommands::write($statement)->role);
        self::assertSame('FLUSH NO_WRITE_TO_BINLOG TABLES `t` WITH READ LOCK', $statement->toString());
    }

    public function testTargetWritesKeywordsAndChannels(): void
    {
        self::assertSame('keyword', FlushCommands::target(ServerFlush::Status)->role);
        self::assertSame('relay-logs', FlushCommands::target(new RelayLogFlush('c'))->role);
    }

    #[TestWith(['FLUSH NO_WRITE_TO_BINLOG LOGS, PRIVILEGES', \SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement::class, 'FLUSH NO_WRITE_TO_BINLOG LOGS, PRIVILEGES'])]
    #[TestWith(['FLUSH RELAY LOGS FOR CHANNEL \'c\'', \SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement::class, 'FLUSH RELAY LOGS FOR CHANNEL \'c\''])]
    #[TestWith(['FLUSH RELAY LOGS', \SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement::class, 'FLUSH RELAY LOGS'])]
    #[TestWith(['FLUSH TABLES t FOR EXPORT', \SqlSemantics\Model\Statement\Server\Administration\FlushTablesForExportStatement::class, 'FLUSH TABLES `t` FOR EXPORT'])]
    #[TestWith(['FLUSH LOCAL TABLES t, t WITH READ LOCK', FlushTablesWithReadLockStatement::class, 'FLUSH NO_WRITE_TO_BINLOG TABLES `t`, `t` WITH READ LOCK'])]
    #[TestWith(['FLUSH TABLES', \SqlSemantics\Model\Statement\Server\Administration\FlushTablesStatement::class, 'FLUSH TABLES'])]
    #[TestWith(['FLUSH TABLES t', \SqlSemantics\Model\Statement\Server\Administration\FlushTablesStatement::class, 'FLUSH TABLES `t`'])]
    #[TestWith(['FLUSH STATUS', \SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement::class, 'FLUSH STATUS'])]
    public function testWriteSpellsEveryFlushForm(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind($sql);
        self::assertTrue($statement instanceof \SqlSemantics\Model\Statement\Server\Administration\FlushTablesStatement || $statement instanceof FlushTablesWithReadLockStatement || $statement instanceof \SqlSemantics\Model\Statement\Server\Administration\FlushTablesForExportStatement || $statement instanceof \SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement);
        self::assertSame([$class, $expected], [$statement::class, FlushCommands::write($statement)->toString()]);
    }
}
