<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\SetDomainDefaultStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetDomainDefaultStatement::class)]
#[Medium]
final class SetDomainDefaultStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER DOMAIN app.d SET DEFAULT 1 + 2');
        self::assertInstanceOf(SetDomainDefaultStatement::class, $statement);
        self::assertSame(['app', 'd'], $statement->domain->parts);
        self::assertSame('ALTER DOMAIN "app"."d" SET DEFAULT(1 + 2)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET DEFAULT 1');
        self::assertInstanceOf(SetDomainDefaultStatement::class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET DEFAULT 1');
        self::assertInstanceOf(SetDomainDefaultStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "e" SET DEFAULT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withDomain(new QualifiedName(['e']))));
        self::assertSame(['d'], $statement->domain->parts);
    }

    public function testWithDefaultReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET DEFAULT 1');
        self::assertInstanceOf(SetDomainDefaultStatement::class, $statement);
        self::assertSame("ALTER DOMAIN \"d\" SET DEFAULT 'x'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withDefault(Expression::literal('x', Dialect::PostgreSql))));
    }

    public function testRejectsADefaultOfAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET DEFAULT 1');
        self::assertInstanceOf(SetDomainDefaultStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDefault(Expression::literal(1, Dialect::MySql));
    }
}
