<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\CloneLocalStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CloneLocalStatement::class)]
#[Medium]
final class CloneLocalStatementTest extends TestCase
{
    public function testWithOriginRetainsSemanticOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CLONE LOCAL DATA DIRECTORY '/tmp/clone'");
        self::assertInstanceOf(CloneLocalStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Scalar\Value\LiteralKind::Text, $statement->directory->literalKind);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE LOCAL DATA DIRECTORY '/tmp/clone'");
        self::assertInstanceOf(CloneLocalStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new CloneLocalStatement($origin, $statement->directory);
    }
}
