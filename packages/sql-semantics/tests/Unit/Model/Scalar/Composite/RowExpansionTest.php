<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Composite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Composite\RowExpansion;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Model\Scalar\Reference\Parameter;
use SqlSemantics\Model\Statement\ValuesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(RowExpansion::class)]
#[Medium]
final class RowExpansionTest extends TestCase
{
    #[TestWith(['VALUES ($1.*)', 'VALUES (($1).*)'])]
    #[TestWith(['SELECT f($1.*)', 'SELECT "f"(($1).*)'])]
    #[TestWith(['SELECT $1.*', 'SELECT ($1).*'])]
    #[TestWith(['SELECT (g()).*', 'SELECT ("g"()).*'])]
    public function testExpandsAParameterOrAFunctionResult(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testInputsContainsTheComposite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('VALUES ($1.*)');
        self::assertInstanceOf(ValuesStatement::class, $statement);
        $expansion = $statement->rows[0][0];
        self::assertInstanceOf(RowExpansion::class, $expansion);
        self::assertInstanceOf(Parameter::class, $expansion->composite);
        self::assertSame([$expansion->composite], $expansion->inputs());
        self::assertSame(ExpressionKind::RowExpansion, $expansion->kind);
    }

    public function testSpellingIsTheExpansionMarker(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT f($1.*)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertSame('.*', $call->arguments[0]->spelling());
    }

    public function testWithFactsKeepsTheComposite(): void
    {
        $composite = Expression::literal(1, Dialect::PostgreSql);
        $expansion = new RowExpansion($composite->facts, $composite->source, $composite);
        $copy = $expansion->withFacts(new ExpressionFacts($expansion->type, Nullability::MaybeNull));
        self::assertNotSame($expansion, $copy);
        self::assertSame($composite, $copy->composite);
    }

    public function testRejectsAnotherDialect(): void
    {
        $composite = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new RowExpansion($composite->facts, $composite->source, $composite);
    }

    public function testRejectsANestedExpansion(): void
    {
        $composite = Expression::literal(1, Dialect::PostgreSql);
        $expansion = new RowExpansion($composite->facts, $composite->source, $composite);
        $this->expectException(InvalidStructure::class);
        new RowExpansion($composite->facts, $composite->source, $expansion);
    }
}
