<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Operator\ArrayCast;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ArrayCast::class)]
#[Medium]
final class ArrayCastTest extends TestCase
{
    #[TestWith(['mysql-8.0.44', 'UNSIGNED', 'bigint'])]
    #[TestWith(['mysql-8.4.7', 'CHAR(8)', 'char'])]
    #[TestWith(['mysql-9.1.0', 'DATE', 'date'])]
    public function testInputsKeepTheConvertedJsonArray(string $version, string $target, string $type): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("CREATE TABLE t (j JSON, INDEX ((CAST(j->'$.v' AS " . $target . ' ARRAY))))');
        $expected = "CREATE TABLE `t`(`j` json, INDEX((CAST((`j` -> '$.v') AS " . $target . ' ARRAY))))';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
        $cast = new ArrayCast($statement->source, Expression::literal('[1]', Dialect::MySql), TypeDescriptor::builtin(Dialect::MySql, $type));
        self::assertSame($type, $cast->type->name);
        self::assertSame(ExpressionKind::Cast, $cast->kind);
        self::assertCount(1, $cast->inputs());
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $value = Expression::literal('[1]', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new ArrayCast($value->source, $value, TypeDescriptor::builtin(Dialect::MySql, 'date'));
    }

    public function testSpellingNamesTheCast(): void
    {
        $value = Expression::literal('[1]', Dialect::MySql);
        self::assertSame('CAST', (new ArrayCast($value->source, $value, TypeDescriptor::builtin(Dialect::MySql, 'date')))->spelling());
    }

    public function testWithFactsPreservesTheElementType(): void
    {
        $value = Expression::literal('[1]', Dialect::MySql);
        $cast = new ArrayCast($value->source, $value, TypeDescriptor::builtin(Dialect::MySql, 'date'));
        $copy = $cast->withFacts($cast->facts);
        self::assertNotSame($cast, $copy);
        self::assertSame($cast->element, $copy->element);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $value = Expression::literal('[1]', Dialect::MySql);
        $cast = new ArrayCast($value->source, $value, TypeDescriptor::builtin(Dialect::MySql, 'date'));
        $this->expectException(InvalidStructure::class);
        $cast->withFacts($value->facts);
    }
}
