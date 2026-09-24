<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\AlterSubscriptionConnectionStatement::class)]
#[Medium]
final class AlterSubscriptionConnectionStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION s CONNECTION 'host=a'");
        self::assertInstanceOf(Statement\AlterSubscriptionConnectionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Alter, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION s CONNECTION 'host=a'");
        self::assertInstanceOf(Statement\AlterSubscriptionConnectionStatement::class, $statement);
        self::assertSame('q', $statement->withName('q')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithConnectionReplacesTheText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION s CONNECTION 'host=a'");
        self::assertInstanceOf(Statement\AlterSubscriptionConnectionStatement::class, $statement);
        self::assertSame('host=b', $statement->withConnection('host=b')->connection);
        self::assertSame('host=a', $statement->connection);
    }
}
