<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\RelayPosition;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RelayPosition::class)]
#[Medium]
final class RelayPositionTest extends TestCase
{
    public function testKeepsTheFileAndPositionLiterals(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA UNTIL RELAY_LOG_FILE = 'f.000001', RELAY_LOG_POS = 0x10");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(RelayPosition::class, $statement->until);
        self::assertSame("'f.000001'", $statement->until->file->text);
        self::assertSame('0x10', $statement->until->position->text);
    }

    public function testRejectsANumericFileName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA UNTIL RELAY_LOG_FILE = 'f', RELAY_LOG_POS = 4");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(RelayPosition::class, $statement->until);
        $this->expectException(InvalidStructure::class);
        new RelayPosition($statement->until->position, $statement->until->position);
    }
}
