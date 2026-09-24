<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Statistics\CreateStatisticsStatement;
use SqlSemantics\Model\Statement\Definition\Statistics\StatisticsInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StatisticsInvariant::class)]
#[Medium]
final class StatisticsInvariantTest extends TestCase
{
    public function testDialectRejectsAnotherDatabaseLanguage(): void
    {
        StatisticsInvariant::dialect((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin);
        $this->expectException(InvalidStructure::class);
        StatisticsInvariant::dialect((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testNameRequiresANameForIfNotExists(): void
    {
        StatisticsInvariant::name(null, false);
        StatisticsInvariant::name(new QualifiedName(['db', 'app', 's']), true);
        $this->expectException(InvalidStructure::class);
        StatisticsInvariant::name(null, true);
    }

    public function testNameRejectsFourComponents(): void
    {
        $this->expectException(InvalidStructure::class);
        StatisticsInvariant::name(new QualifiedName(['a', 'b', 'c', 'd']), false);
    }

    public function testColumnDistinguishesColumnsFromExpressions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('CREATE STATISTICS ON a, (b + 1), zz FROM t', strict: false);
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        self::assertSame([true, false, true], array_map(StatisticsInvariant::column(...), $statement->elements));
    }

    public function testElementsRejectsARepeatedExpression(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('CREATE STATISTICS ON a, (b + 1) FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        StatisticsInvariant::elements($statement->elements);
        $this->expectException(InvalidStructure::class);
        StatisticsInvariant::elements([$statement->elements[1], $statement->elements[1]]);
    }

    public function testElementsRejectsMoreThanEightElements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('CREATE STATISTICS ON (a + 1), (a + 2), (a + 3), (a + 4), (a + 5), (a + 6), (a + 7), (a + 8) FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        StatisticsInvariant::elements([...$statement->elements, Expression::literal(1, Dialect::PostgreSql)]);
    }

    public function testElementsRejectsAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        StatisticsInvariant::elements([Expression::literal(1, Dialect::MySql)]);
    }

    public function testKeyIdentifiesColumnsByName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('CREATE STATISTICS ON a, (t.b), zz, (b * 2) FROM t', strict: false);
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        self::assertSame(['column:a', 'column:b', 'column:zz', 'expression:("b" * 2)'], array_map(StatisticsInvariant::key(...), $statement->elements));
    }
}
