<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Scalar\Reference\VariableAssignment;
use SqlSemantics\Model\Statement\Execution\DoExpressionsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(VariableAssignment::class)]
#[Medium]
final class VariableAssignmentTest extends TestCase
{
    public function testInputsOrdersTheTargetBeforeTheValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('DO @x := 1', strict: false);
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        $assignment = $statement->expressions[0];
        self::assertInstanceOf(VariableAssignment::class, $assignment);
        $target = $assignment->target;
        self::assertInstanceOf(UnresolvedVariableReference::class, $target);
        self::assertSame('x', $target->name);
        self::assertSame('1', $assignment->value->spelling());
        self::assertSame([$target, $assignment->value], $assignment->inputs());
        self::assertSame('integer', $assignment->type->name);
        self::assertSame(Nullability::NotNull, $assignment->nullability);
        self::assertSame('DO(@`x` := 1)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('DO(@`x` := 1)', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)));
    }

    public function testSpellingIsTheAssignmentOperator(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $target = new UnresolvedVariableReference(new ExpressionFacts(new TypeDescriptor(Dialect::MySql, BuiltinIdentity::Unknown), Nullability::Unknown), $value->source, 'x', VariableScope::User);
        $assignment = new VariableAssignment($value->facts, $value->source, $target, $value);
        self::assertSame(':=', $assignment->spelling());
        self::assertSame('(@`x` := 1)', $assignment->structure()->toString());
    }

    public function testRejectsAValueFromAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $target = new UnresolvedVariableReference(new ExpressionFacts(new TypeDescriptor(Dialect::MySql, BuiltinIdentity::Unknown), Nullability::Unknown), $value->source, 'x', VariableScope::User);
        $this->expectException(InvalidStructure::class);
        new VariableAssignment($value->facts, $value->source, $target, Expression::literal(1, Dialect::Sqlite));
    }

    public function testWithFactsKeepsTheTargetAndValue(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $target = new UnresolvedVariableReference(new ExpressionFacts(new TypeDescriptor(Dialect::MySql, BuiltinIdentity::Unknown), Nullability::Unknown), $value->source, 'x', VariableScope::User);
        $assignment = new VariableAssignment($value->facts, $value->source, $target, $value);
        $copy = $assignment->withFacts(new ExpressionFacts($assignment->type, Nullability::MaybeNull));
        self::assertNotSame($assignment, $copy);
        self::assertSame($target, $copy->target);
        self::assertSame($value, $copy->value);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $assignment->nullability);
    }
}
