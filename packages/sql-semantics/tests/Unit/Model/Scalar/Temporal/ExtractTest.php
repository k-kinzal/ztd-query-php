<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Temporal\Extract;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Scalar\Temporal\PostgreSqlField;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Extract::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ExtractTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, "SELECT EXTRACT(YEAR FROM TIMESTAMP '2020-01-01')", PostgreSqlField::Year, 'numeric'])]
    #[TestWith([Dialect::MySql, "SELECT EXTRACT(DAY_SECOND FROM '2020-01-01')", MySqlUnit::DaySecond, 'bigint'])]
    public function testInputsRetainsTheExtractionField(Dialect $dialect, string $sql, PostgreSqlField|MySqlUnit $field, string $type): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $extract = $query->outputs[0]->expression;
        self::assertInstanceOf(Extract::class, $extract);
        self::assertSame($field, $extract->field);
        self::assertSame([$extract->value], $extract->inputs());
        self::assertSame($type, $extract->type->name);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectsAFieldFromAnotherDialect(): void
    {
        $value = Expression::literal('2020-01-01', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new Extract($value->source, PostgreSqlField::Year, $value);
    }

    public function testWithFactsRejectsAContradictoryResult(): void
    {
        $value = Expression::literal('2020-01-01', Dialect::PostgreSql);
        $extract = new Extract($value->source, PostgreSqlField::Year, $value);
        $this->expectException(InvalidStructure::class);
        $extract->withFacts($value->facts);
    }

    public function testWithFactsPreservesTheFieldAndInput(): void
    {
        $value = Expression::literal('2020-01-01', Dialect::PostgreSql);
        $extract = new Extract($value->source, PostgreSqlField::Year, $value);
        $copy = $extract->withFacts($extract->facts);
        self::assertNotSame($extract, $copy);
        self::assertSame($value, $copy->value);
        self::assertSame(PostgreSqlField::Year, $copy->field);
    }

    public function testSpellingIdentifiesTheExtractionOperation(): void
    {
        $value = Expression::literal(null, Dialect::PostgreSql);
        $extract = new Extract($value->source, PostgreSqlField::Year, $value);
        self::assertSame('EXTRACT', $extract->spelling());
        self::assertSame(Nullability::AlwaysNull, $extract->nullability);
    }

    public function testInputsAreReboundAfterAnImmutableChange(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT /* original */ EXTRACT(YEAR FROM CURRENT_TIMESTAMP)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $extract = $query->outputs[0]->expression;
        self::assertInstanceOf(Extract::class, $extract);
        $copy = $query->replaceExpression($extract->value, Expression::literal(null, Dialect::PostgreSql));
        self::assertSame('SELECT EXTRACT(YEAR FROM NULL)', $copy->toString());
        self::assertSame(Nullability::AlwaysNull, $copy->outputs[0]->expression->nullability);
        self::assertSame(Nullability::MaybeNull, $extract->nullability);
    }
}
