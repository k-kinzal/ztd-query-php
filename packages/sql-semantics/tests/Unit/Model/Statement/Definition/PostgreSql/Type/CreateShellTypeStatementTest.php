<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\CreateShellTypeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateShellTypeStatement::class)]
#[Medium]
final class CreateShellTypeStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE app.box3d');
        self::assertInstanceOf(CreateShellTypeStatement::class, $statement);
        self::assertSame(['app', 'box3d'], $statement->name->parts);
        self::assertSame('CREATE TYPE "app"."box3d"', $statement->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t');
        self::assertInstanceOf(CreateShellTypeStatement::class, $statement);
        self::assertSame($statement->toString(), $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t');
        self::assertInstanceOf(CreateShellTypeStatement::class, $statement);
        self::assertSame('CREATE TYPE "u"', $statement->withName(new QualifiedName(['u']))->toString());
        self::assertSame(['t'], $statement->name->parts);
    }

    public function testRejectsAnOverQualifiedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t');
        self::assertInstanceOf(CreateShellTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c']));
    }
}
