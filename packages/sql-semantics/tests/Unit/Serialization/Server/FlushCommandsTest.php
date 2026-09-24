<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
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
}
