<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\RefreshSubscriptionStatement::class)]
#[Medium]
final class RefreshSubscriptionStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s REFRESH PUBLICATION');
        self::assertInstanceOf(Statement\RefreshSubscriptionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Alter, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s REFRESH PUBLICATION');
        self::assertInstanceOf(Statement\RefreshSubscriptionStatement::class, $statement);
        self::assertSame('q', $statement->withName('q')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOptionsAcceptsOnlyCopyData(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s REFRESH PUBLICATION');
        self::assertInstanceOf(Statement\RefreshSubscriptionStatement::class, $statement);
        self::assertSame('ALTER SUBSCRIPTION "s" REFRESH PUBLICATION WITH (copy_data = true)', $statement->withOptions(new Operand\SubscriptionOptions(copyData: true))->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withOptions(new Operand\SubscriptionOptions(refresh: true));
    }
}
