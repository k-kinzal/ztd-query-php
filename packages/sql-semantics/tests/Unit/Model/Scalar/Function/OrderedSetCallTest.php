<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Function\DeclaredFunction;
use SqlSemantics\Model\Scalar\Function\OrderedSetCall;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(OrderedSetCall::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OrderedSetCallTest extends TestCase
{
    public function testSeparatesDirectArgumentsFromOrderedInputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(score INTEGER)')))->bind('SELECT app.percentile(0.5) WITHIN GROUP (ORDER BY score DESC NULLS FIRST) FILTER (WHERE score>0) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(OrderedSetCall::class, $aggregate);
        self::assertSame('0.5', $aggregate->directArguments[0]->spelling());
        self::assertInstanceOf(Expression::class, $aggregate->withinGroup[0]->key);
        self::assertSame('score', $aggregate->withinGroup[0]->key->columnBinding()?->column->name);
        self::assertTrue($aggregate->withinGroup[0]->descending);
        self::assertTrue($aggregate->withinGroup[0]->nullsFirst);
        self::assertSame('>', $aggregate->filter?->spelling());
        self::assertStringContainsString('WITHIN GROUP(ORDER BY "score" DESC NULLS FIRST)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testUsesOrderedInputsWhenResolvingTheRegisteredSignature(): void
    {
        $numeric = TypeDescriptor::builtin(Dialect::PostgreSql, 'numeric');
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $signature = new FunctionSignature('percentile', [$numeric, $integer], $integer, aggregate: true);
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions($signature);
        $statement = (new Binder($schema))->bind('SELECT percentile(0.5) WITHIN GROUP (ORDER BY $1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(OrderedSetCall::class, $aggregate);
        self::assertInstanceOf(DeclaredFunction::class, $aggregate->function);
        self::assertSame($signature, $aggregate->function->signature);
        self::assertInstanceOf(Expression::class, $aggregate->withinGroup[0]->key);
        self::assertSame('integer', $aggregate->withinGroup[0]->key->type->name);
        self::assertSame('integer', $aggregate->type->name);
    }

    public function testRequiresAnOrderedInputBeforeSerialization(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(OrderedSetCall::class, $aggregate);
        $this->expectException(InvalidStructure::class);
        new OrderedSetCall($aggregate->facts, $aggregate->source, $aggregate->function, $aggregate->directArguments, []);
    }

    public function testWithFactsPreservesBothArgumentGroups(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(OrderedSetCall::class, $aggregate);
        $copy = $aggregate->withFacts($aggregate->facts);
        self::assertNotSame($aggregate, $copy);
        self::assertSame($aggregate->directArguments, $copy->directArguments);
        self::assertSame($aggregate->withinGroup, $copy->withinGroup);
        self::assertSame($aggregate->function, $copy->function);
    }


    public function testInputsListDirectArgumentsThenOrderedKeysThenTheFilter(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT app.percentile(0.5, 2) WITHIN GROUP (ORDER BY score DESC) FILTER (WHERE score > 0) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(OrderedSetCall::class, $aggregate);
        $inputs = $aggregate->inputs();
        self::assertCount(4, $inputs);
        self::assertSame($aggregate->directArguments, [$inputs[0], $inputs[1]]);
        self::assertSame($aggregate->withinGroup[0]->key, $inputs[2]);
        self::assertSame($aggregate->filter, $inputs[3]);
    }

    public function testSpellingUppercasesTheQualifiedName(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT app.percentile(0.5) WITHIN GROUP (ORDER BY score), percentile_cont(0.5) WITHIN GROUP (ORDER BY score) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $qualified = $statement->outputs[0]->expression;
        self::assertInstanceOf(OrderedSetCall::class, $qualified);
        $builtin = $statement->outputs[1]->expression;
        self::assertInstanceOf(OrderedSetCall::class, $builtin);
        self::assertSame('APP.PERCENTILE', $qualified->spelling());
        self::assertSame('PERCENTILE_CONT', $builtin->spelling());
    }

    public function testInputsSpreadEveryDirectArgumentAndOrderedKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT rank(1, 2) WITHIN GROUP (ORDER BY a, b) FILTER (WHERE a > 0) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(OrderedSetCall::class, $aggregate);
        self::assertSame(['1', '2', 'a', 'b', 'a > 0'], array_map(static fn (Expression $input): string => trim($input->source->toString()), $aggregate->inputs()));
    }
}
