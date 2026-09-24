<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\DropDomainDefaultStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropDomainDefaultStatement::class)]
#[Medium]
final class DropDomainDefaultStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN app.d DROP DEFAULT');
        self::assertInstanceOf(DropDomainDefaultStatement::class, $statement);
        self::assertSame(['app', 'd'], $statement->domain->parts);
        self::assertSame('ALTER DOMAIN "app"."d" DROP DEFAULT', $statement->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP DEFAULT');
        self::assertInstanceOf(DropDomainDefaultStatement::class, $statement);
        self::assertSame($statement->toString(), $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP DEFAULT');
        self::assertInstanceOf(DropDomainDefaultStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "e" DROP DEFAULT', $statement->withDomain(new QualifiedName(['e']))->toString());
        self::assertSame(['d'], $statement->domain->parts);
    }

    public function testRejectsAnOverQualifiedDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d DROP DEFAULT');
        self::assertInstanceOf(DropDomainDefaultStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDomain(new QualifiedName(['a', 'b', 'c']));
    }
}
