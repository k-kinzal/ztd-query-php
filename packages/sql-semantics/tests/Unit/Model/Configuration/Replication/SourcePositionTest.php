<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\SourcePosition;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SourcePosition::class)]
#[Medium]
final class SourcePositionTest extends TestCase
{
    public function testKeepsTheFileAndPositionLiterals(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA UNTIL SOURCE_LOG_FILE = 'f.000001', SOURCE_LOG_POS = 016");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(SourcePosition::class, $statement->until);
        self::assertSame("'f.000001'", $statement->until->file->text);
        self::assertSame('016', $statement->until->position->text);
    }

    public function testRejectsANumericFileName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA UNTIL SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(SourcePosition::class, $statement->until);
        $this->expectException(InvalidStructure::class);
        new SourcePosition($statement->until->position, $statement->until->position);
    }
}
