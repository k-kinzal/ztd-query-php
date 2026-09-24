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
        self::assertSame("SELECT POSITION('a' IN 'cat')", $query->toString());
        self::assertSame("SELECT POSITION('a' IN 'cat')", $binder->bind($query->toString())->toString());
    }

    public function testWriteSpellsATrimInItsFromForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.1.0'))->build());
        $query = $binder->bind("SELECT TRIM('a' FROM 'b'), TRIM('c')");
        self::assertSame("SELECT TRIM(BOTH 'a' FROM 'b'), TRIM(BOTH FROM 'c')", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testWriteSpellsTheNormalFormOfANormalizationAndItsTest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT NORMALIZE('a'), 'b' IS NOT NFKD NORMALIZED = true");
        self::assertSame("SELECT NORMALIZE('a', NFC), ((('b') IS NOT NFKD NORMALIZED) = true)", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testWriteSpellsTheFullTextSearchModifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (title TEXT)'));
        $query = $binder->bind("SELECT MATCH title AGAINST ('w') FROM t");
        self::assertSame("SELECT MATCH(`title`) AGAINST('w' IN NATURAL LANGUAGE MODE) FROM `t`", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
