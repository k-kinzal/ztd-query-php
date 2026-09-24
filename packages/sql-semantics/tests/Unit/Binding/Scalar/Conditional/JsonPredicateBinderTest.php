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
}
