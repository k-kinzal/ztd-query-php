<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Operator\CollatedExpression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(CollatedExpression::class)]
#[Medium]
final class CollatedExpressionTest extends TestCase
{
    public function testInputsContainsOnlyTheCollatedOperand(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT \'a\' COLLATE "C"');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $collated = $statement->outputs[0]->expression;
        self::assertInstanceOf(CollatedExpression::class, $collated);
        self::assertSame(['C'], $collated->collation->parts);
        self::assertSame("'a'", $collated->operand->spelling());
        self::assertSame([$collated->operand], $collated->inputs());
        self::assertSame(Nullability::NotNull, $collated->nullability);
        self::assertSame('SELECT (\'a\' COLLATE "C")', $statement->toString());
        self::assertSame('SELECT (\'a\' COLLATE "C")', $binder->bind($statement->toString())->toString());
    }

    public function testSpellingIsTheCollateKeyword(): void
    {
        $operand = Expression::literal('a', Dialect::PostgreSql);
        $collated = new CollatedExpression($operand->facts, $operand->source, $operand, new QualifiedName(['pg_catalog', 'C']));
        self::assertSame('COLLATE', $collated->spelling());
        self::assertSame('(\'a\' COLLATE "pg_catalog"."C")', $collated->structure()->toString());
    }

    public function testRejectsAnOperandFromAnotherDialect(): void
    {
        $facts = Expression::literal('a', Dialect::PostgreSql);
        $operand = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new CollatedExpression($facts->facts, $operand->source, $operand, new QualifiedName(['C']));
    }

    public function testWithFactsKeepsTheOperandAndCollation(): void
    {
        $operand = Expression::literal('a', Dialect::PostgreSql);
        $collation = new QualifiedName(['C']);
        $collated = new CollatedExpression($operand->facts, $operand->source, $operand, $collation);
        $copy = $collated->withFacts(new ExpressionFacts($collated->type, Nullability::MaybeNull));
        self::assertNotSame($collated, $copy);
        self::assertSame($operand, $copy->operand);
        self::assertSame($collation, $copy->collation);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $collated->nullability);
    }
}
