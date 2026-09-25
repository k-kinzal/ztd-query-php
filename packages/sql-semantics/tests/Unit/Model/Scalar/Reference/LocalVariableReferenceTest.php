<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\ReturnStatement;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(LocalVariableReference::class)]
#[Medium]
final class LocalVariableReferenceTest extends TestCase
{
    public function testInputsAreEmpty(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
        $reference = $statement->body->value;
        self::assertInstanceOf(LocalVariableReference::class, $reference);
        self::assertSame([], $reference->inputs());
        self::assertSame(ExpressionKind::LocalVariable, $reference->kind);
    }

    public function testSpellingIsTheVariableName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(Amount INT) RETURNS INT RETURN amount');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
        $reference = $statement->body->value;
        self::assertInstanceOf(LocalVariableReference::class, $reference);
        self::assertSame('Amount', $reference->spelling());
    }

    public function testWithFactsKeepsTheVariable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
        $reference = $statement->body->value;
        self::assertInstanceOf(LocalVariableReference::class, $reference);
        $copy = $reference->withFacts(new ExpressionFacts($reference->type, Nullability::NotNull));
        self::assertSame($reference->variable, $copy->variable);
        self::assertSame(Nullability::NotNull, $copy->nullability);
    }

    public function testRejectsAnotherType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
        $reference = $statement->body->value;
        self::assertInstanceOf(LocalVariableReference::class, $reference);
        $this->expectException(InvalidStructure::class);
        new LocalVariableReference(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'text'), Nullability::MaybeNull), $reference->source, $reference->variable);
    }

    public function testTakesPrecedenceOverAColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind('CREATE FUNCTION f(n CHAR(1)) RETURNS INT RETURN (SELECT COUNT(*) FROM t WHERE n = 1)');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertCount(1, array_filter(\SqlSemantics\Model\Traversal\Expressions::all($statement), static fn ($expression): bool => $expression instanceof LocalVariableReference));
        self::assertStringContainsString('WHERE (`n` = 1)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
