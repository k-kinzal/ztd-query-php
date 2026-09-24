<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class UnresolvedColumnReferenceTest extends TestCase
{
    /**
     * @param list<string> $name Identifier parts tested against unresolved-reference invariants
     */
    #[TestWith([BuiltinIdentity::Unknown, []])]
    #[TestWith([BuiltinIdentity::Integer, ['missing']])]
    public function testRequiresNamesAndUnknownType(BuiltinIdentity $identity, array $name): void
    {
        $source = new \SqlParser\Parser\Node('expression', 0, []);
        $facts = new ExpressionFacts(new TypeDescriptor(Dialect::PostgreSql, $identity), Nullability::Unknown);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference($facts, $source, $name);
    }

    public function testRetainsTheUnresolvedNameForLaterBinding(): void
    {
        $boundQuery1 = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT missing', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        $expression = $boundQuery1->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $expression);
        self::assertSame(['missing'], $expression->name);
        self::assertSame(BuiltinIdentity::Unknown, $expression->type->identity);
    }

    public function testInputsHasNoOperands(): void
    {
        $reference = \SqlSemantics\Model\Expression::reference(['a', 'b'], Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $reference);
        self::assertSame([], $reference->inputs());
        self::assertSame([], $reference->lineage());
    }

    public function testSpellingJoinsTheNameParts(): void
    {
        $reference = \SqlSemantics\Model\Expression::reference(['a', 'b'], Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $reference);
        self::assertSame('a.b', $reference->spelling());
        self::assertSame('"a"."b"', $reference->structure()->toString());
    }

    public function testReferencePartsReturnsTheNameParts(): void
    {
        $reference = \SqlSemantics\Model\Expression::reference(['a', 'b'], Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $reference);
        self::assertSame(['a', 'b'], $reference->referenceParts());
        self::assertSame($reference->name, $reference->referenceParts());
    }

    public function testWithFactsKeepsTheNameWhileTheTypeStaysUnknown(): void
    {
        $reference = \SqlSemantics\Model\Expression::reference(['a'], Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $reference);
        $copy = $reference->withFacts(new ExpressionFacts($reference->type, Nullability::MaybeNull));
        self::assertNotSame($reference, $copy);
        self::assertSame(['a'], $copy->name);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::Unknown, $reference->nullability);
    }

    public function testWithFactsRejectsAResolvedType(): void
    {
        $reference = \SqlSemantics\Model\Expression::reference(['a'], Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $reference);
        $this->expectException(InvalidStructure::class);
        $reference->withFacts(new ExpressionFacts(new TypeDescriptor(Dialect::PostgreSql, BuiltinIdentity::Integer), Nullability::NotNull));
    }
}
