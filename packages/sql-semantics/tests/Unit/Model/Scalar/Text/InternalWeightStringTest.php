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
use SqlSemantics\Model\Scalar\Text\InternalWeightString;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(InternalWeightString::class)]
#[Medium]
final class InternalWeightStringTest extends TestCase
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
    public function testInputsKeepTheNumericArguments(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a VARCHAR(10))'));
        $query = $binder->bind('SELECT WEIGHT_STRING(a, 1, 0x2, 3) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $weights = $query->outputs[0]->expression;
        self::assertInstanceOf(InternalWeightString::class, $weights);
        self::assertSame([[$weights->operand], 1, 2, 3], [$weights->inputs(), $weights->resultLength, $weights->codepoints, $weights->flags]);
        $written = (new SimpleSerializer())->serialize($query);
        self::assertSame('SELECT WEIGHT_STRING(`a`, 1, 2, 3) FROM `t`', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testSpellingNamesTheFunction(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        self::assertSame('WEIGHT_STRING', (new InternalWeightString($value->source, $value, 0, 0, 0))->spelling());
    }

    public function testWithFactsKeepsTheNumbers(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $weights = new InternalWeightString($value->source, $value, 4, 2, 64);
        $copy = $weights->withFacts($weights->facts);
        self::assertSame([4, 2, 64], [$copy->resultLength, $copy->codepoints, $copy->flags]);
    }

    public function testWithFactsRejectsOtherFacts(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        (new InternalWeightString($value->source, $value, 0, 0, 0))->withFacts($value->facts);
    }

    public function testInputsRejectNegativeNumbers(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new InternalWeightString($value->source, $value, -1, 0, 0);
    }

    public function testInputsRejectOtherDialects(): void
    {
        $value = Expression::literal('a', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new InternalWeightString($value->source, $value, 0, 0, 0);
    }
}
