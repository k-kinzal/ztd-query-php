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
use SqlSemantics\Model\Scalar\Function\AggregateCall;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(AggregateCall::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class AggregateCallTest extends TestCase
{
    public function testInputsListArgumentsThenOrderingKeysThenTheFilter(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT count(DISTINCT n ORDER BY n) FILTER (WHERE n > 0) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(AggregateCall::class, $aggregate);
        $inputs = $aggregate->inputs();
        self::assertCount(3, $inputs);
        self::assertSame($aggregate->arguments[0], $inputs[0]);
        self::assertSame($aggregate->orderBy[0]->key, $inputs[1]);
        self::assertSame($aggregate->filter, $inputs[2]);
        self::assertSame(ExpressionKind::Aggregate, $aggregate->kind);
    }

    public function testSpellingUppercasesTheQualifiedName(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $signature = new FunctionSignature('total', [$integer], $integer, Nullability::NotNull, aggregate: true, schema: 'app');
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)')->withFunctions($signature);
        $statement = (new Binder($schema))->bind('SELECT app.total(DISTINCT n) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(AggregateCall::class, $aggregate);
        self::assertSame('APP.TOTAL', $aggregate->spelling());
        self::assertSame(['app', 'total'], $aggregate->function->name()->parts);
    }

    public function testWithFactsKeepsEveryOperandAndLeavesTheOriginalUnchanged(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT count(DISTINCT n ORDER BY n) FILTER (WHERE n > 0) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(AggregateCall::class, $aggregate);
        $changed = $aggregate->withFacts(new ExpressionFacts($aggregate->type, Nullability::AlwaysNull));
        self::assertNotSame($aggregate, $changed);
        self::assertSame(Nullability::AlwaysNull, $changed->nullability);
        self::assertSame(Nullability::NotNull, $aggregate->nullability);
        self::assertSame($aggregate->function, $changed->function);
        self::assertSame($aggregate->arguments, $changed->arguments);
        self::assertSame($aggregate->mode, $changed->mode);
        self::assertSame($aggregate->orderBy, $changed->orderBy);
        self::assertSame($aggregate->filter, $changed->filter);
    }

    public function testRejectsArgumentsFromAnotherDialect(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT count(DISTINCT n) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(AggregateCall::class, $aggregate);
        $this->expectException(InvalidStructure::class);
        new AggregateCall($aggregate->facts, $aggregate->source, $aggregate->function, [Expression::literal(1, Dialect::MySql)], $aggregate->mode, [], null);
    }

    public function testSerializesModeOrderingAndFilter(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $binder = new Binder($schema);
        $statement = $binder->bind('SELECT count(DISTINCT n ORDER BY n) FILTER (WHERE n > 0) FROM t');
        self::assertSame('SELECT "count"(DISTINCT "n" ORDER BY "n" ASC) FILTER (WHERE ("n" > 0)) FROM "public"."t"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
