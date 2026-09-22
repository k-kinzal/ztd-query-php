<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Notification\UnlistenStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UnlistenStatement::class)]
#[Medium]
final class UnlistenStatementTest extends TestCase
{
    public function testWithOriginRetainsSemanticOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('UNLISTEN events');
        self::assertInstanceOf(UnlistenStatement::class, $statement);
        self::assertSame('events', $statement->channel);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('UNLISTEN events');
        self::assertInstanceOf(UnlistenStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new UnlistenStatement($origin, $statement->channel);
    }
}
