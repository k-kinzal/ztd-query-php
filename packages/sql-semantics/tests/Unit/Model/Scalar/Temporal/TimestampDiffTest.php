<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Scalar\Temporal\TimestampDiff;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(TimestampDiff::class)]
#[Medium]
final class TimestampDiffTest extends TestCase
{
    public function testKeepsTheUnitAndBothInputs(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t(a DATE, b DATETIME)'));
        $query = $binder->bind('SELECT TIMESTAMPDIFF(MONTH, a, b) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(TimestampDiff::class, $call);
        self::assertSame(MySqlUnit::Month, $call->unit);
        self::assertSame(['a', 'b'], [$call->start->columnBinding()?->column->name, $call->end->columnBinding()?->column->name]);
        self::assertSame(['bigint', Nullability::MaybeNull], [$call->type->name, $call->nullability]);
        self::assertSame(ExpressionKind::TimestampDiff, $call->kind);
        self::assertSame('SELECT TIMESTAMPDIFF(MONTH, `a`, `b`) FROM `t`', $query->toString());
    }

    public function testInputsListTheStartThenTheEnd(): void
    {
        $start = Expression::literal('2020-01-01', Dialect::MySql);
        $end = Expression::literal('2021-01-01', Dialect::MySql);
        self::assertSame([$start, $end], (new TimestampDiff(new \SqlParser\Parser\Node('expr', 0, []), MySqlUnit::Year, $start, $end))->inputs());
    }

    public function testSpellingNamesTheFunction(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        self::assertSame('TIMESTAMPDIFF', (new TimestampDiff(new \SqlParser\Parser\Node('expr', 0, []), MySqlUnit::Year, $value, $value))->spelling());
    }

    public function testWithFactsKeepsTheDerivedFacts(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $call = new TimestampDiff(new \SqlParser\Parser\Node('expr', 0, []), MySqlUnit::Year, $value, $value);
        self::assertSame($call->unit, $call->withFacts($call->facts)->unit);
        $this->expectException(InvalidStructure::class);
        $call->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($call->type, Nullability::NotNull));
    }

    public function testRejectsACompoundUnit(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new TimestampDiff(new \SqlParser\Parser\Node('expr', 0, []), MySqlUnit::DayHour, $value, $value);
    }
}
