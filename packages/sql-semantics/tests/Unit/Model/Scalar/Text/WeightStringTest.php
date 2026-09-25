<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Text\WeightLevel;
use SqlSemantics\Model\Scalar\Text\WeightPadding;
use SqlSemantics\Model\Scalar\Text\WeightString;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(WeightString::class)]
#[Medium]
final class WeightStringTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testInputsKeepThePadding(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a VARCHAR(10))'));
        $query = $binder->bind('SELECT WEIGHT_STRING(a AS CHAR(3)), WEIGHT_STRING(a AS BINARY(4)), WEIGHT_STRING(a) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $weights = $query->outputs[0]->expression;
        self::assertInstanceOf(WeightString::class, $weights);
        self::assertSame([$weights->operand], $weights->inputs());
        self::assertInstanceOf(WeightPadding::class, $weights->padding);
        self::assertFalse($weights->padding->binary);
        $written = (new SimpleSerializer())->serialize($query);
        self::assertSame('SELECT WEIGHT_STRING(`a` AS CHAR(3)), WEIGHT_STRING(`a` AS BINARY(4)), WEIGHT_STRING(`a`) FROM `t`', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testInputsKeepTheMySql5Levels(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a VARCHAR(10))'));
        $query = $binder->bind('SELECT WEIGHT_STRING(a AS CHAR(3) LEVEL 1, 9 DESC REVERSE), WEIGHT_STRING(a LEVEL 1-3) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $weights = $query->outputs[0]->expression;
        self::assertInstanceOf(WeightString::class, $weights);
        self::assertSame([6, true, true], [$weights->levels[1]->first, $weights->levels[1]->descending, $weights->levels[1]->reverse]);
        $written = (new SimpleSerializer())->serialize($query);
        self::assertSame('SELECT WEIGHT_STRING(`a` AS CHAR(3) LEVEL 1, 6 DESC REVERSE), WEIGHT_STRING(`a` LEVEL 1-3) FROM `t`', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testSpellingNamesTheFunction(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        self::assertSame('WEIGHT_STRING', (new WeightString($value->source, $value))->spelling());
    }

    public function testWithFactsKeepsTheOperands(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $weights = new WeightString($value->source, $value, new WeightPadding(false, 2), [new WeightLevel(1, 1)]);
        $copy = $weights->withFacts($weights->facts);
        self::assertNotSame($weights, $copy);
        self::assertSame([$weights->padding, $weights->levels], [$copy->padding, $copy->levels]);
    }

    public function testWithFactsRejectsOtherFacts(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        (new WeightString($value->source, $value))->withFacts($value->facts);
    }

    public function testInputsRejectLevelsWithBinaryPadding(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new WeightString($value->source, $value, new WeightPadding(true, 2), [new WeightLevel(1, 1)]);
    }

    public function testInputsRejectARangeBesideOtherLevels(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new WeightString($value->source, $value, null, [new WeightLevel(1, 3), new WeightLevel(4, 4)]);
    }

    public function testInputsRejectOtherDialects(): void
    {
        $value = Expression::literal('a', Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new WeightString($value->source, $value);
    }
}
