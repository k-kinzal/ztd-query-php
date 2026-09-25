<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Text\CodeFunctions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Model\Scalar\Text\CharacterCodes;
use SqlSemantics\Model\Scalar\Text\InternalWeightString;
use SqlSemantics\Model\Scalar\Text\WeightString;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CodeFunctions::class)]
#[Medium]
final class CodeFunctionsTest extends TestCase
{
    public function testBindLeavesOtherFunctionsAlone(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT LENGTH('a')");
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(FunctionCall::class, $query->outputs[0]->expression);
    }

    public function testCodesReadsTheCharacterSetInLowercase(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CHAR(65 USING LATIN1)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $codes = $query->outputs[0]->expression;
        self::assertInstanceOf(CharacterCodes::class, $codes);
        self::assertSame('latin1', $codes->characterSet);
    }

    public function testWeightsDistinguishesTheInternalForm(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT WEIGHT_STRING('a', 0, 1, 2), WEIGHT_STRING('a' AS BINARY(2))");
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(InternalWeightString::class, $query->outputs[0]->expression);
        $padded = $query->outputs[1]->expression;
        self::assertInstanceOf(WeightString::class, $padded);
        self::assertSame(true, $padded->padding?->binary);
    }

    public function testLevelsReadsARangeAndClampsItsBounds(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("SELECT WEIGHT_STRING('a' LEVEL 0-9)");
        self::assertInstanceOf(BoundSelect::class, $query);
        $weights = $query->outputs[0]->expression;
        self::assertInstanceOf(WeightString::class, $weights);
        self::assertSame([1, 6], [$weights->levels[0]->first, $weights->levels[0]->last]);
    }

    public function testNumberReadsHexadecimalArguments(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT WEIGHT_STRING('a', 0x10, 1, 2)");
        self::assertInstanceOf(BoundSelect::class, $query);
        $weights = $query->outputs[0]->expression;
        self::assertInstanceOf(InternalWeightString::class, $weights);
        self::assertSame(16, $weights->resultLength);
    }
}
