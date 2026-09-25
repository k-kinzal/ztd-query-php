<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\TextExpressions;

#[CoversClass(TextExpressions::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TextExpressionsTest extends TestCase
{
    public function testWritePreservesOperandRoles(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT POSITION('a' IN 'cat')");
        self::assertSame("SELECT POSITION('a' IN 'cat')", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame("SELECT POSITION('a' IN 'cat')", (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testWriteSpellsATrimInItsFromForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.1.0'))->build());
        $query = $binder->bind("SELECT TRIM('a' FROM 'b'), TRIM('c')");
        self::assertSame("SELECT TRIM(BOTH 'a' FROM 'b'), TRIM(BOTH FROM 'c')", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testWriteSpellsTheNormalFormOfANormalizationAndItsTest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT NORMALIZE('a'), 'b' IS NOT NFKD NORMALIZED = true");
        self::assertSame("SELECT NORMALIZE('a', NFC), ((('b') IS NOT NFKD NORMALIZED) = true)", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testWriteSpellsTheFullTextSearchModifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (title TEXT)'));
        $query = $binder->bind("SELECT MATCH title AGAINST ('w') FROM t");
        self::assertSame("SELECT MATCH(`title`) AGAINST('w' IN NATURAL LANGUAGE MODE) FROM `t`", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testCodesWritesCharAndWeightString(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT CHAR(65 USING utf8mb4), WEIGHT_STRING('a' AS CHAR(2)), WEIGHT_STRING('a', 0, 2, 64)");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $codes = $query->outputs[0]->expression;
        $padded = $query->outputs[1]->expression;
        $internal = $query->outputs[2]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Text\CharacterCodes::class, $codes);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Text\WeightString::class, $padded);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Text\InternalWeightString::class, $internal);
        self::assertSame(['CHAR(65 USING `utf8mb4`)', "WEIGHT_STRING('a' AS CHAR(2))", "WEIGHT_STRING('a', 0, 2, 64)"], [TextExpressions::codes($codes)->toString(), TextExpressions::codes($padded)->toString(), TextExpressions::codes($internal)->toString()]);
    }
}
