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


    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testKeepsTheGroupConcatModeOrderingAndSeparator(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a TEXT, b INT)'));
        $query = $binder->bind("SELECT group_concat(DISTINCT a, b ORDER BY b DESC, a SEPARATOR ';') FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(AggregateCall::class, $call);
        self::assertCount(2, $call->arguments);
        self::assertCount(2, $call->orderBy);
        self::assertSame("';'", $call->separator?->text);
        self::assertSame("SELECT group_concat(DISTINCT `a`, `b` ORDER BY `b` DESC, `a` ASC SEPARATOR ';') FROM `t`", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testRejectsASeparatorOutsideGroupConcat(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)'));
        $query = $binder->bind("SELECT count(a), 'x' FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        $separator = $query->outputs[1]->expression;
        self::assertInstanceOf(AggregateCall::class, $call);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $separator);
        $this->expectException(InvalidStructure::class);
        new AggregateCall($call->facts, $call->source, $call->function, $call->arguments, $call->mode, $call->orderBy, $call->filter, $separator);
    }

    public function testConcatenationRecognizesOnlyMySqlGroupConcat(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT)')))->bind('SELECT GROUP_CONCAT(a ORDER BY 1), COUNT(a) FROM t');
        self::assertInstanceOf(BoundSelect::class, $mysql);
        $concatenation = $mysql->outputs[0]->expression;
        $count = $mysql->outputs[1]->expression;
        self::assertInstanceOf(AggregateCall::class, $concatenation);
        self::assertInstanceOf(AggregateCall::class, $count);
        self::assertTrue(AggregateCall::concatenation($concatenation->function, $concatenation->facts));
        self::assertFalse(AggregateCall::concatenation($count->function, $count->facts));
        self::assertSame([$concatenation->arguments[0]], $concatenation->inputs());
    }

    public function testOrderingPositionMustReferToAnArgumentOfGroupConcat(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT)')))->bind('SELECT GROUP_CONCAT(a ORDER BY 1), COUNT(a) FROM t');
        self::assertInstanceOf(BoundSelect::class, $mysql);
        $concatenation = $mysql->outputs[0]->expression;
        $count = $mysql->outputs[1]->expression;
        self::assertInstanceOf(AggregateCall::class, $concatenation);
        self::assertInstanceOf(AggregateCall::class, $count);
        $this->expectException(InvalidStructure::class);
        new AggregateCall($count->facts, $count->source, $count->function, $count->arguments, $count->mode, $concatenation->orderBy, null);
    }
}
