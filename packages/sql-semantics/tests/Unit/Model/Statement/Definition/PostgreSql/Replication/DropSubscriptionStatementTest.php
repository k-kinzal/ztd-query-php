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

#[CoversClass(Statement\DropSubscriptionStatement::class)]
#[Medium]
final class DropSubscriptionStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SUBSCRIPTION s');
        self::assertInstanceOf(Statement\DropSubscriptionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Drop, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SUBSCRIPTION s');
        self::assertInstanceOf(Statement\DropSubscriptionStatement::class, $statement);
        self::assertSame('q', $statement->withName('q')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithIfExistsToleratesAMissingSubscription(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SUBSCRIPTION s');
        self::assertInstanceOf(Statement\DropSubscriptionStatement::class, $statement);
        self::assertSame('DROP SUBSCRIPTION IF EXISTS "s"', $statement->withIfExists(true)->toString());
    }

    public function testWithBehaviorWritesTheDependencyBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SUBSCRIPTION s');
        self::assertInstanceOf(Statement\DropSubscriptionStatement::class, $statement);
        self::assertSame('DROP SUBSCRIPTION "s" CASCADE', $statement->withBehavior(\SqlSemantics\Model\Definition\DropBehavior::Cascade)->toString());
    }
}
