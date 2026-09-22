<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Notification\NotifyStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NotifyStatement::class)]
#[Medium]
final class NotifyStatementTest extends TestCase
{
    public function testWithOriginRetainsSemanticOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("NOTIFY events, 'changed'");
        self::assertInstanceOf(NotifyStatement::class, $statement);
        self::assertSame('events', $statement->channel);
        self::assertNotNull($statement->payload);
        self::assertSame(\SqlSemantics\Model\Scalar\Value\LiteralKind::Text, $statement->payload->literalKind);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("NOTIFY events, 'changed'");
        self::assertInstanceOf(NotifyStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new NotifyStatement($origin, $statement->channel, $statement->payload);
    }
}
