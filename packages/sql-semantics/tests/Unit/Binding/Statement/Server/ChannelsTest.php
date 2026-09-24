<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Channels;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Administration\RelayLogFlush;
use SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Channels::class)]
#[Medium]
final class ChannelsTest extends TestCase
{
    public function testReadDecodesTheChannelOrReturnsNull(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("FLUSH RELAY LOGS FOR CHANNEL 'a''b', RELAY LOGS");
        self::assertInstanceOf(FlushServerStatement::class, $statement);
        self::assertInstanceOf(RelayLogFlush::class, $statement->targets[0]);
        self::assertInstanceOf(RelayLogFlush::class, $statement->targets[1]);
        self::assertSame("a'b", $statement->targets[0]->channel);
        self::assertNull($statement->targets[1]->channel);
    }

    public function testReadDiagnosesALineFeed(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("FLUSH RELAY LOGS FOR CHANNEL 'a\\nb'");
    }
}
