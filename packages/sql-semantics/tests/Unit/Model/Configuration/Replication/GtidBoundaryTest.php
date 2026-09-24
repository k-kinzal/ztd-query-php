<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\GtidBoundary;
use SqlSemantics\Model\Configuration\Replication\GtidUntil;
use SqlSemantics\Model\Configuration\Replication\SourcePosition;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GtidBoundary::class)]
#[Medium]
final class GtidBoundaryTest extends TestCase
{
    public function testKeepsTheBoundaryAndSet(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA UNTIL SQL_BEFORE_GTIDS = 'uuid:1-3'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(GtidBoundary::class, $statement->until);
        self::assertSame(GtidUntil::Before, $statement->until->boundary);
        self::assertSame("'uuid:1-3'", $statement->until->gtids->text);
    }

    public function testRejectsANumericSet(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA UNTIL SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(SourcePosition::class, $statement->until);
        $this->expectException(InvalidStructure::class);
        new GtidBoundary(GtidUntil::After, $statement->until->position);
    }
}
