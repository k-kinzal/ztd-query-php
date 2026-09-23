<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\TypedLiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Operator\CastExpression;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TypedLiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypedLiteralBinderTest extends TestCase
{
    #[TestWith(["TIMESTAMP 'not a date'", 'timestamp', "CAST('not a date' AS timestamp)"])]
    #[TestWith(["TIMESTAMP(3) WITH TIME ZONE '2020-01-01'", 'timestamptz', "CAST('2020-01-01' AS timestamp(3) WITH TIME ZONE)"])]
    #[TestWith(["INTERVAL '1' DAY TO SECOND(2)", 'interval', "CAST('1' AS INTERVAL DAY TO SECOND(2))"])]
    #[TestWith(["INTERVAL(4) '1 day'", 'interval', "CAST('1 day' AS INTERVAL(4))"])]
    #[TestWith(["NUMERIC(10,2) '3.50'", 'numeric', "CAST('3.50' AS numeric(10, 2))"])]
    #[TestWith(["CHARACTER VARYING(5) 'hello'", 'varchar', "CAST('hello' AS varchar(5))"])]
    public function testBindPreservesTypeParametersWithoutEvaluatingTheLiteral(string $input, string $type, string $serialized): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT ' . $input);
        self::assertInstanceOf(BoundSelect::class, $query);
        $cast = $query->outputs[0]->expression;
        self::assertInstanceOf(CastExpression::class, $cast);
        self::assertSame($type, $cast->type->name);
        self::assertSame('SELECT ' . $serialized, $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    #[TestWith(["app.measure(10, 2) 'x'", 'app.measure', 2])]
    #[TestWith(["app.measure 'x'", 'app.measure', 0])]
    #[TestWith(["\"Custom\" 'x'", 'Custom', 0])]
    public function testNamedRetainsTheTypeReferenceAndModifiers(string $input, string $type, int $modifiers): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT ' . $input);
        self::assertInstanceOf(BoundSelect::class, $query);
        $cast = $query->outputs[0]->expression;
        self::assertInstanceOf(CastExpression::class, $cast);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\NamedIdentity::class, $cast->type->identity);
        self::assertSame($type, $cast->type->name);
        self::assertCount($modifiers, $cast->type->identity->arguments);
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
