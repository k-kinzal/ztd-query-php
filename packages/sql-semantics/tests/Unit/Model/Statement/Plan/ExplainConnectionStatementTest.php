<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\MySqlFormat;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Plan\ExplainConnectionStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

#[CoversClass(ExplainConnectionStatement::class)]
#[Medium]
final class ExplainConnectionStatementTest extends TestCase
{
    public function testBindsTheConnectionNumberAndFormat(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $plain = $binder->bind('EXPLAIN FOR CONNECTION 42');
        $json = $binder->bind('EXPLAIN FORMAT=JSON FOR CONNECTION 42');
        self::assertInstanceOf(ExplainConnectionStatement::class, $plain);
        self::assertInstanceOf(ExplainConnectionStatement::class, $json);
        self::assertSame('42', $plain->connection->spelling);
        self::assertSame(MySqlFormat::Default, $plain->format);
        self::assertSame(MySqlFormat::Json, $json->format);
        self::assertSame(StatementKind::Explain, $json->kind);
        self::assertSame('EXPLAIN FOR CONNECTION 42', $plain->toString());
        self::assertSame('EXPLAIN FORMAT = JSON FOR CONNECTION 42', $json->toString());
    }

    public function testWithOriginPreservesTheConnectionAndFormat(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('EXPLAIN FORMAT=JSON FOR CONNECTION 42');
        self::assertInstanceOf(ExplainConnectionStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::MySql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->connection, $copy->connection);
        self::assertSame(MySqlFormat::Json, $copy->format);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAFractionalConnectionNumber(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('EXPLAIN FOR CONNECTION 42');
        self::assertInstanceOf(ExplainConnectionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ExplainConnectionStatement($statement->origin, new NumericParameter('4.2'));
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('EXPLAIN FOR CONNECTION 42');
        self::assertInstanceOf(ExplainConnectionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ExplainConnectionStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->connection);
    }
}
