<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Locale;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\CopyCollationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyCollationStatement::class)]
#[Medium]
final class CopyCollationStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE COLLATION c (from = pg_catalog."POSIX")');
        self::assertInstanceOf(CopyCollationStatement::class, $statement);
        self::assertSame(['pg_catalog', 'POSIX'], $statement->copied->parts);
        self::assertSame('CREATE COLLATION "c" FROM "pg_catalog"."POSIX"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnOverQualifiedSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE COLLATION c FROM "C"');
        self::assertInstanceOf(CopyCollationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withCopied(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE COLLATION c FROM "C"');
        self::assertInstanceOf(CopyCollationStatement::class, $statement);
        self::assertSame('CREATE COLLATION "c" FROM "C"', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE COLLATION c FROM "C"');
        self::assertInstanceOf(CopyCollationStatement::class, $statement);
        self::assertSame('CREATE COLLATION "d" FROM "C"', $statement->withName(new QualifiedName(['d']))->toString());
    }

    public function testWithCopiedReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE COLLATION c FROM "C"');
        self::assertInstanceOf(CopyCollationStatement::class, $statement);
        self::assertSame(['POSIX'], $statement->withCopied(new QualifiedName(['POSIX']))->copied->parts);
        self::assertSame(['C'], $statement->copied->parts);
    }

    public function testWithIfNotExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE COLLATION c FROM "C"');
        self::assertInstanceOf(CopyCollationStatement::class, $statement);
        self::assertTrue($statement->withIfNotExists(true)->ifNotExists);
    }
}
