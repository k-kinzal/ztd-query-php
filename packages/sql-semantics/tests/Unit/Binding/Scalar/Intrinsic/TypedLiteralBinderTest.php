<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\TypedLiteralBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Operator\CastExpression;
use SqlSemantics\Model\Validation\InputViolation;
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
        self::assertSame('SELECT ' . $serialized, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
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
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    #[TestWith(["int4 '1'", "CAST('1' AS integer)"])]
    #[TestWith(["Int4 '1'", "CAST('1' AS integer)"])]
    #[TestWith(["float8 '1.5'", "CAST('1.5' AS double precision)"])]
    #[TestWith(["uuid 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'", "CAST('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11' AS uuid)"])]
    #[TestWith(["jsonb '{}'", "CAST('{}' AS jsonb)"])]
    #[TestWith(["\"int4\" '1'", "CAST('1' AS \"int4\")"])]
    #[TestWith(["pg_catalog.int4 '1'", "CAST('1' AS \"pg_catalog\".\"int4\")"])]
    #[TestWith(["regclass 'x'", "CAST('x' AS \"regclass\")"])]
    public function testNamedSeparatesBuiltinSpellingsFromTypeReferences(string $input, string $serialized): void
    {
        self::assertSame('SELECT ' . $serialized, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ' . $input)));
    }

    public function testNamedReadsABuiltinDeclarationDirectly(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse("SELECT int4 '1'")->find('AexprConst')[0];
        $name = $source->find('func_name')[0];
        $declaration = new Node('literal_type', 0, [$name]);
        self::assertSame('integer', TypedLiteralBinder::named($name, $source, $declaration, new Scope(new Identifiers(Dialect::PostgreSql)))->name);
    }

    public function testBindRejectsAnOrderedTypeModifierList(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TypeModifier->message());
        $binder->bind("SELECT app.t(1 ORDER BY 1) 'x'");
    }

    #[TestWith([Dialect::PostgreSql, "SELECT 'x'", 'AexprConst'])]
    #[TestWith([Dialect::MySql, "SELECT DATE '2020-01-01'", 'temporal_literal'])]
    public function testBindReturnsNullWithoutAPostgreSqlTypedConstant(Dialect $dialect, string $sql, string $rule): void
    {
        $source = (new DialectParser($dialect))->parse($sql)->find($rule)[0];
        self::assertNull(TypedLiteralBinder::bind($source, new Scope(new Identifiers($dialect))));
    }
}
