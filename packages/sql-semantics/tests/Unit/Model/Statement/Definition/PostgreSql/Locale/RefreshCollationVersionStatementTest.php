<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Locale;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\RefreshCollationVersionStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RefreshCollationVersionStatement::class)]
#[Medium]
final class RefreshCollationVersionStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER COLLATION "de-x-icu" REFRESH VERSION');
        self::assertInstanceOf(RefreshCollationVersionStatement::class, $statement);
        self::assertSame(['de-x-icu'], $statement->collation->parts);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertSame('ALTER COLLATION "de-x-icu" REFRESH VERSION', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnOverQualifiedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER COLLATION c REFRESH VERSION');
        self::assertInstanceOf(RefreshCollationVersionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withCollation(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER COLLATION c REFRESH VERSION');
        self::assertInstanceOf(RefreshCollationVersionStatement::class, $statement);
        self::assertSame('ALTER COLLATION "c" REFRESH VERSION', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithCollationReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER COLLATION c REFRESH VERSION');
        self::assertInstanceOf(RefreshCollationVersionStatement::class, $statement);
        self::assertSame('ALTER COLLATION "s"."d" REFRESH VERSION', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withCollation(new QualifiedName(['s', 'd']))));
        self::assertSame(['c'], $statement->collation->parts);
    }
}
