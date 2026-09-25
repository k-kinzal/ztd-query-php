<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Conditional\JsonPredicateBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Conditional\JsonPredicate;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(JsonPredicateBinder::class)]
#[Medium]
final class JsonPredicateBinderTest extends TestCase
{
    public function testBindKeepsTheOperandNullability(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a JSONB)')))->bind('SELECT a IS JSON OBJECT FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $predicate = $statement->outputs[0]->expression;
        self::assertInstanceOf(JsonPredicate::class, $predicate);
        self::assertSame(['a', Nullability::MaybeNull], [$predicate->operand->columnBinding()?->column->name, $predicate->nullability]);
    }

    #[TestWith(['SELECT 1 IS JSON'])]
    #[TestWith(['SELECT CURRENT_DATE IS NOT JSON'])]
    public function testBindRejectsANonTextualOperand(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::JsonPredicateOperand->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    #[TestWith(['text', true])]
    #[TestWith(['bytea', true])]
    #[TestWith(['jsonb', true])]
    #[TestWith(['integer', false])]
    #[TestWith(['boolean', false])]
    public function testTextualAcceptsTextJsonAndBytes(string $type, bool $expected): void
    {
        self::assertSame($expected, JsonPredicateBinder::textual(TypeDescriptor::builtin(Dialect::PostgreSql, $type)));
    }

    #[TestWith(['select a is not json array with unique keys from t', \SqlSemantics\Model\Scalar\Conditional\JsonItemKind::JsonArray, true, true, 'SELECT ("a" IS NOT JSON ARRAY WITH UNIQUE KEYS) FROM "public"."t"'])]
    #[TestWith(['SELECT a IS JSON WITHOUT UNIQUE KEYS FROM t', \SqlSemantics\Model\Scalar\Conditional\JsonItemKind::Value, false, false, 'SELECT ("a" IS JSON VALUE) FROM "public"."t"'])]
    #[TestWith(['SELECT a IS JSON OBJECT FROM t', \SqlSemantics\Model\Scalar\Conditional\JsonItemKind::JsonObject, false, false, 'SELECT ("a" IS JSON OBJECT) FROM "public"."t"'])]
    #[TestWith(['SELECT a IS JSON SCALAR WITH UNIQUE FROM t', \SqlSemantics\Model\Scalar\Conditional\JsonItemKind::Scalar, false, true, 'SELECT ("a" IS JSON SCALAR WITH UNIQUE KEYS) FROM "public"."t"'])]
    public function testBindReadsTheItemKindNegationAndKeyUniqueness(string $sql, \SqlSemantics\Model\Scalar\Conditional\JsonItemKind $kind, bool $negated, bool $unique, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $predicate = $statement->outputs[0]->expression;
        self::assertInstanceOf(JsonPredicate::class, $predicate);
        self::assertSame([$kind, $negated, $unique], [$predicate->itemKind, $predicate->negated, $predicate->uniqueKeys]);
        self::assertSame(Nullability::MaybeNull, $predicate->nullability);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindIgnoresExpressionsWithoutAJsonConstraint(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT 1 + 2, 'a' || 'b'");
        self::assertSame("SELECT (1 + 2), ('a' || 'b')", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['integer', false])]
    #[TestWith(['numeric(10,2)', false])]
    #[TestWith(['timestamp(3)', false])]
    #[TestWith(['interval', false])]
    #[TestWith(['integer[]', false])]
    #[TestWith(['varchar(10)', true])]
    #[TestWith(['bit(3)', true])]
    public function testTextualRejectsNumericTemporalAndArrayStorage(string $type, bool $expected): void
    {
        $node = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT CAST(NULL AS ' . $type . ')')->find('Typename')[0];
        self::assertSame($expected, JsonPredicateBinder::textual((new \SqlSemantics\Ast\TypeReader(Dialect::PostgreSql))->read($node)));
    }
}
