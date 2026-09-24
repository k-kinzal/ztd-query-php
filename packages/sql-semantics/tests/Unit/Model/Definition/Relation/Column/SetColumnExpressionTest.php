<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Column\SetColumnExpression::class)]
#[Medium]
final class SetColumnExpressionTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, twice INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN twice SET EXPRESSION AS (id * 2)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\SetColumnExpression::class, $statement->actions[0]);
        self::assertSame('twice', $statement->actions[0]->column);
        self::assertSame('("id" * 2)', $statement->actions[0]->expression->structure()->toString());
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "twice" SET EXPRESSION AS(("id" * 2))', $statement->toString());
    }

    public function testRejectsAnExpressionFromAnotherDatabaseLanguage(): void
    {
        $expression = \SqlSemantics\Model\Expression::literal(1, Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new Relation\Column\SetColumnExpression('twice', $expression);
    }
}
