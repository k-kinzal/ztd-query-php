<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Model\Scalar\Function\UnresolvedFunction;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(FunctionCall::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FunctionCallTest extends TestCase
{
    public function testInputsAreTheArgumentsInOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertSame($call->arguments, $call->inputs());
        self::assertSame(['1', '2'], array_map(static fn (Expression $argument): ?string => $argument->spelling(), $call->inputs()));
        self::assertSame(ExpressionKind::Function, $call->kind);
    }

    public function testSpellingUppercasesTheQualifiedName(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT app.lower(n) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertSame('APP.LOWER', $call->spelling());
        self::assertInstanceOf(UnresolvedFunction::class, $call->function);
        self::assertSame(['app', 'lower'], $call->function->name()->parts);
    }

    public function testWithFactsKeepsTheReferenceAndArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        $changed = $call->withFacts(new ExpressionFacts($call->type, Nullability::NotNull));
        self::assertNotSame($call, $changed);
        self::assertSame(Nullability::NotNull, $changed->nullability);
        self::assertSame(Nullability::Unknown, $call->nullability);
        self::assertSame($call->function, $changed->function);
        self::assertSame($call->arguments, $changed->arguments);
    }

    public function testRejectsArgumentsFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        $this->expectException(InvalidStructure::class);
        new FunctionCall($call->facts, $call->source, $call->function, [Expression::literal(1, Dialect::Sqlite)]);
    }

    public function testSerializesTheQualifiedCall(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $binder = new Binder($schema);
        $statement = $binder->bind('SELECT app.lower(n) FROM t');
        self::assertSame('SELECT "app"."lower"("n") FROM "public"."t"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
