<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\JsonItemKind;
use SqlSemantics\Model\Scalar\Conditional\JsonPredicate;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonPredicate::class)]
#[Medium]
final class JsonPredicateTest extends TestCase
{
    #[TestWith(['SELECT a IS JSON FROM t', JsonItemKind::Value, false, false, 'SELECT ("a" IS JSON VALUE) FROM "public"."t"'])]
    #[TestWith(['SELECT a IS NOT JSON ARRAY FROM t', JsonItemKind::JsonArray, true, false, 'SELECT ("a" IS NOT JSON ARRAY) FROM "public"."t"'])]
    #[TestWith(['SELECT a IS JSON OBJECT WITH UNIQUE FROM t', JsonItemKind::JsonObject, false, true, 'SELECT ("a" IS JSON OBJECT WITH UNIQUE KEYS) FROM "public"."t"'])]
    #[TestWith(['SELECT a IS JSON SCALAR WITHOUT UNIQUE KEYS FROM t', JsonItemKind::Scalar, false, false, 'SELECT ("a" IS JSON SCALAR) FROM "public"."t"'])]
    public function testBindsTheItemTypeNegationAndUniqueness(string $sql, JsonItemKind $itemKind, bool $negated, bool $uniqueKeys, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT NOT NULL)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $predicate = $statement->outputs[0]->expression;
        self::assertInstanceOf(JsonPredicate::class, $predicate);
        self::assertSame([$itemKind, $negated, $uniqueKeys, 'boolean', Nullability::NotNull], [$predicate->itemKind, $predicate->negated, $predicate->uniqueKeys, $predicate->type->name, $predicate->nullability]);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testInputsContainsTheOperand(): void
    {
        $operand = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame([$operand], (new JsonPredicate($operand->facts, $operand->source, $operand))->inputs());
    }

    public function testSpellingNamesTheNegation(): void
    {
        $operand = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame('IS NOT JSON', (new JsonPredicate($operand->facts, $operand->source, $operand, negated: true))->spelling());
    }

    public function testWithFactsKeepsTheTest(): void
    {
        $operand = Expression::literal('{}', Dialect::PostgreSql);
        $predicate = new JsonPredicate($operand->facts, $operand->source, $operand, JsonItemKind::JsonObject, true, true);
        $copy = $predicate->withFacts(new ExpressionFacts($predicate->type, Nullability::MaybeNull));
        self::assertSame([$operand, JsonItemKind::JsonObject, true, true, Nullability::MaybeNull], [$copy->operand, $copy->itemKind, $copy->negated, $copy->uniqueKeys, $copy->nullability]);
    }

    public function testRejectsAnotherDialect(): void
    {
        $operand = Expression::literal('{}', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new JsonPredicate($operand->facts, $operand->source, $operand);
    }
}
