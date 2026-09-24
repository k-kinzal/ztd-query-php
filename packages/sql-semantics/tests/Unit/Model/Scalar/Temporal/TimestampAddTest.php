<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Scalar\Temporal\TimestampAdd;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TimestampAdd::class)]
#[Medium]
final class TimestampAddTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'SELECT TIMESTAMPADD(DAY, 1, d) FROM t', MySqlUnit::Day, 'datetime'])]
    #[TestWith(['mysql-8.4.7', 'SELECT TIMESTAMPADD(SQL_TSI_MONTH, 2, e) FROM t', MySqlUnit::Month, 'date'])]
    public function testKeepsTheUnitQuantityAndInput(string $version, string $sql, MySqlUnit $unit, string $type): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(d DATETIME, e DATE)'));
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(TimestampAdd::class, $call);
        self::assertSame($unit, $call->unit);
        self::assertSame($type, $call->type->name);
        self::assertSame(ExpressionKind::TimestampAdd, $call->kind);
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsListTheQuantityThenTheInput(): void
    {
        $quantity = Expression::literal(1, Dialect::MySql);
        $value = Expression::literal('2020-01-01', Dialect::MySql);
        $call = new TimestampAdd(new \SqlParser\Parser\Node('expr', 0, []), MySqlUnit::Hour, $quantity, $value, DateArithmeticRules::Current);
        self::assertSame([$quantity, $value], $call->inputs());
    }

    public function testSpellingNamesTheFunction(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        self::assertSame('TIMESTAMPADD', (new TimestampAdd(new \SqlParser\Parser\Node('expr', 0, []), MySqlUnit::Hour, $value, $value, DateArithmeticRules::Current))->spelling());
    }

    public function testWithFactsKeepsTheDerivedFacts(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $call = new TimestampAdd(new \SqlParser\Parser\Node('expr', 0, []), MySqlUnit::Hour, $value, $value, DateArithmeticRules::Current);
        $copy = $call->withFacts($call->facts);
        self::assertNotSame($call, $copy);
        self::assertSame($call->unit, $copy->unit);
        $this->expectException(InvalidStructure::class);
        $call->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($call->type, $call->nullability));
    }

    public function testRejectsOperandsOfAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new TimestampAdd(new \SqlParser\Parser\Node('expr', 0, []), MySqlUnit::Day, $value, $value, DateArithmeticRules::Current);
    }
}
