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
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Window\NamedWindow;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(WindowCall::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class WindowCallTest extends TestCase
{
    public function testInputsStartWithTheCallFollowedByWindowExpressions(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT row_number() OVER (PARTITION BY n ORDER BY n) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $window = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $window);
        self::assertInstanceOf(WindowSpecification::class, $window->window);
        $inputs = $window->inputs();
        self::assertCount(3, $inputs);
        self::assertSame($window->function, $inputs[0]);
        self::assertSame($window->window->partitionBy[0], $inputs[1]);
        self::assertSame($window->window->orderBy[0]->key, $inputs[2]);
        self::assertSame(ExpressionKind::Window, $window->kind);
    }

    public function testSpellingDelegatesToTheWrappedCall(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT row_number() OVER (ORDER BY n) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $window = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $window);
        self::assertInstanceOf(FunctionCall::class, $window->function);
        self::assertSame('ROW_NUMBER', $window->spelling());
        self::assertSame($window->function->spelling(), $window->spelling());
    }

    public function testANamedWindowContributesNoExpressions(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT sum(n) OVER w FROM t WINDOW w AS (ORDER BY n)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $window = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $window);
        self::assertInstanceOf(NamedWindow::class, $window->window);
        self::assertSame('w', $window->window->name);
        self::assertSame([$window->function], $window->inputs());
    }

    public function testWithFactsKeepsTheCallAndWindow(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT sum(n) OVER (ORDER BY n) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $window = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $window);
        $changed = $window->withFacts(new ExpressionFacts($window->type, Nullability::AlwaysNull));
        self::assertNotSame($window, $changed);
        self::assertSame(Nullability::AlwaysNull, $changed->nullability);
        self::assertNotSame(Nullability::AlwaysNull, $window->nullability);
        self::assertSame($window->function, $changed->function);
        self::assertSame($window->window, $changed->window);
    }

    public function testRejectsWindowExpressionsFromAnotherDialect(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT sum(n) OVER (ORDER BY n) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $window = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $window);
        $foreign = new WindowSpecification(null, [Expression::literal(1, Dialect::MySql)], [], null);
        $this->expectException(InvalidStructure::class);
        new WindowCall($window->facts, $window->source, $window->function, $foreign);
    }

    public function testSerializesTheOverClause(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $binder = new Binder($schema);
        $statement = $binder->bind('SELECT row_number() OVER (PARTITION BY n ORDER BY n) FROM t');
        self::assertSame('SELECT "row_number"() OVER (PARTITION BY "n" ORDER BY "n" ASC) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
