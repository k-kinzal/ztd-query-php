<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Assertion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Assertion\CreateAssertionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateAssertionStatement::class)]
#[Medium]
final class CreateAssertionStatementTest extends TestCase
{
    public function testBindsTheConditionAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)'));
        $statement = $binder->bind('CREATE ASSERTION app.positive CHECK (NOT EXISTS (SELECT 1 FROM t WHERE a < 0)) DEFERRABLE INITIALLY DEFERRED');
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        self::assertSame([['app', 'positive'], CheckingTime::DeferrableDeferred], [$statement->name->parts, $statement->checking]);
        self::assertSame('CREATE ASSERTION "app"."positive" CHECK ((NOT EXISTS(SELECT 1 FROM "public"."t" WHERE ("a" < 0)))) DEFERRABLE INITIALLY DEFERRED', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAConditionOfAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true)');
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateAssertionStatement($statement->origin, $statement->name, Expression::literal(true, Dialect::MySql), CheckingTime::Immediate);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true)');
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE ASSERTION "a" CHECK (true)', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true)');
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true)');
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        self::assertSame('CREATE ASSERTION "b" CHECK (true)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['b']))));
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c', 'd']));
    }

    public function testWithConditionReplacesTheCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true)');
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        self::assertSame('CREATE ASSERTION "a" CHECK (FALSE)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withCondition(Expression::literal(false, Dialect::PostgreSql))));
        self::assertSame('CREATE ASSERTION "a" CHECK (true)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithCheckingReplacesTheCheckingTime(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ASSERTION a CHECK (true)');
        self::assertInstanceOf(CreateAssertionStatement::class, $statement);
        self::assertSame('CREATE ASSERTION "a" CHECK (true) DEFERRABLE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withChecking(CheckingTime::DeferrableImmediate)));
        self::assertSame(CheckingTime::Immediate, $statement->checking);
    }
}
