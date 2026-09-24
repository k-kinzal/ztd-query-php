<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\Operators;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operators::class)]
#[Medium]
final class OperatorsTest extends TestCase
{
    public function testCreateKeepsTheLastArgumentAndIgnoresUnknownAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (function = f, rightarg = integer, "Hashes", function = g, gtcmp = >)');
        self::assertInstanceOf(Statement\CreateOperatorStatement::class, $statement);
        self::assertSame([OperatorAttribute::RightArg, OperatorAttribute::Function, OperatorAttribute::Merges], array_map(static fn ($option) => $option->attribute, $statement->options));
        self::assertSame(['g'], $statement->options[1]->value instanceof \SqlSemantics\Model\Relation\QualifiedName ? $statement->options[1]->value->parts : null);
    }

    #[TestWith(['CREATE OPERATOR === (function = f, leftarg = integer)', 'definition-requirement'])]
    #[TestWith(['CREATE OPERATOR === (function = f, rightarg = integer, commutator)', 'definition-argument'])]
    #[TestWith(['CREATE OPERATOR === (function = f, rightarg = SETOF integer)', 'set-of-declaration'])]
    #[TestWith(['ALTER OPERATOR = (integer, integer) SET (function = f)', 'definition-attribute'])]
    #[TestWith(['ALTER OPERATOR = (integer, integer) SET (sort1 = <)', 'definition-attribute'])]
    #[TestWith(['ALTER OPERATOR = (integer, integer) SET (hashes = 2)', 'definition-argument'])]
    public function testCreateAndAlterDiagnoseImpossibleDefinitions(string $sql, string $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::from($violation)->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testAlterKeepsTheLastArgument(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR = (integer, integer) SET (restrict = a, join = b, restrict = NONE)');
        self::assertInstanceOf(Statement\AlterOperatorStatement::class, $statement);
        self::assertSame(OperatorAttribute::Restrict, $statement->options[1]->attribute);
        self::assertNull($statement->options[1]->value);
    }

    public function testAttributeResolvesAliasesAndIgnoresOtherCases(): void
    {
        self::assertSame(OperatorAttribute::Function, Operators::attribute('procedure'));
        self::assertSame(OperatorAttribute::Merges, Operators::attribute('sort2'));
        self::assertNull(Operators::attribute('LEFTARG'));
        self::assertNull(Operators::attribute('unknown'));
    }

    public function testOptionRejectsASetOfOperand(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SetOfDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (function = f, leftarg = SETOF text, rightarg = integer)');
    }

    public function testDropReadsEveryOperator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR IF EXISTS + (integer, integer), s.- (NONE, integer) CASCADE');
        self::assertInstanceOf(Statement\DropOperatorsStatement::class, $statement);
        self::assertCount(2, $statement->operators);
        self::assertSame(['s', '-'], $statement->operators[1]->name->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
    }

    public function testDropRejectsAnIncompleteSignature(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::OperatorSignature->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR + (integer)');
    }
}
