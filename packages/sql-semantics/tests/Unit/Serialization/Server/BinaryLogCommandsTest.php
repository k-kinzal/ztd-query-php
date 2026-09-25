<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\BinaryLogReset;
use SqlSemantics\Model\Configuration\Administration\QueryCacheReset;
use SqlSemantics\Model\Configuration\Administration\ReplicaReset;
use SqlSemantics\Model\Statement\Server\Administration\ResetServerStatement;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsBeforeStatement;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsToStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Server\BinaryLogCommands;

#[CoversClass(BinaryLogCommands::class)]
#[Medium]
final class BinaryLogCommandsTest extends TestCase
{
    public function testPurgeWritesTheBinaryLogsSpelling(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build());
        $named = $binder->bind("PURGE MASTER LOGS TO 'a'");
        $dated = $binder->bind('PURGE MASTER LOGS BEFORE 1');
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $named);
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $dated);
        self::assertSame("PURGE BINARY LOGS TO 'a'", (new \SqlSemantics\SimpleSerializer())->serialize($named));
        self::assertSame('PURGE BINARY LOGS BEFORE 1', (new \SqlSemantics\SimpleSerializer())->serialize($dated));
        self::assertSame('purge', BinaryLogCommands::purge($dated)->role);
    }

    public function testResetWritesTheReleaseVocabulary(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('RESET SLAVE, MASTER');
        self::assertInstanceOf(ResetServerStatement::class, $statement);
        self::assertSame('reset', BinaryLogCommands::reset($statement)->role);
        self::assertSame('RESET SLAVE, MASTER', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testTargetSpellsEachOptionForTheRelease(): void
    {
        self::assertSame('reset-replica', BinaryLogCommands::target(new ReplicaReset(), 80044)->role);
        self::assertSame('reset-binary-logs', BinaryLogCommands::target(new BinaryLogReset(), 80407)->role);
        self::assertSame('keyword', BinaryLogCommands::target(new QueryCacheReset(), 50744)->role);
    }
}
