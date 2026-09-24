<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ScalarSubqueryTest extends TestCase
{
    public function testRetainsTheQueryAndItsResultTypeWithoutExecutingIt(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (SELECT 1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $value);
        self::assertSame('1', $value->query->resultColumns()[0]->expression->spelling());
        self::assertSame(BuiltinIdentity::Integer, $value->type->identity);
    }


    public function testInputsAreTheSingleResultColumn(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (SELECT 1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $value);
        self::assertSame([$value->query->resultColumns()[0]->expression], $value->inputs());
        self::assertSame(ExpressionKind::Subquery, $value->kind);
    }

    public function testSpellingIsScalar(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (SELECT 1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $value);
        self::assertSame('SCALAR', $value->spelling());
        self::assertSame('SELECT (SELECT 1)', $query->toString());
    }

    public function testWithFactsKeepsTheQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (SELECT 1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $value);
        $changed = $value->withFacts(new ExpressionFacts($value->type, Nullability::AlwaysNull, ['j0']));
        self::assertNotSame($value, $changed);
        self::assertSame(Nullability::AlwaysNull, $changed->nullability);
        self::assertSame(['j0'], $changed->nullExtendedBy);
        self::assertSame([], $value->nullExtendedBy);
        self::assertSame($value->query, $changed->query);
    }

    public function testSubqueryReturnsTheBoundQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (SELECT 1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $value);
        self::assertSame($value->query, $value->subquery());
        self::assertCount(1, $value->subquery()->resultColumns());
    }

    public function testRejectsAQueryWithSeveralColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT (SELECT 1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $value);
        $wide = $binder->bind('SELECT 8, 9');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $wide);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Scalar\Query\ScalarSubquery($value->facts, $value->source, $wide);
    }

    public function testRejectsAQueryFromAnotherDialect(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (SELECT 1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $value);
        $foreign = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $foreign);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Scalar\Query\ScalarSubquery($value->facts, $value->source, $foreign);
    }
}
