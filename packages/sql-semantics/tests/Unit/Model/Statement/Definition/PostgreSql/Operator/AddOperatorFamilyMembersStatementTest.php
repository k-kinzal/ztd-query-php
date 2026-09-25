<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\OperatorMember;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\SupportFunctionMember;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\AddOperatorFamilyMembersStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddOperatorFamilyMembersStatement::class)]
#[Medium]
final class AddOperatorFamilyMembersStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER OPERATOR FAMILY s.f USING btree ADD OPERATOR 3 = (integer, bigint), FUNCTION 1 (integer, bigint) btint48cmp');
        self::assertInstanceOf(AddOperatorFamilyMembersStatement::class, $statement);
        self::assertSame(['s', 'f'], $statement->family->parts);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertSame('ALTER OPERATOR FAMILY "s"."f" USING "btree" ADD OPERATOR 3 = (integer, bigint), FUNCTION 1(integer, bigint) "btint48cmp"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnOperatorWithoutOperandTypes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree ADD FUNCTION 1 g');
        self::assertInstanceOf(AddOperatorFamilyMembersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withMembers([new OperatorMember(1, new QualifiedName(['<']))]);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree ADD FUNCTION 1 g');
        self::assertInstanceOf(AddOperatorFamilyMembersStatement::class, $statement);
        self::assertSame('ALTER OPERATOR FAMILY "f" USING "btree" ADD FUNCTION 1 "g"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithFamilyReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree ADD FUNCTION 1 g');
        self::assertInstanceOf(AddOperatorFamilyMembersStatement::class, $statement);
        self::assertSame(['e'], $statement->withFamily(new QualifiedName(['e']))->family->parts);
        self::assertSame(['f'], $statement->family->parts);
    }

    public function testWithMethodReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree ADD FUNCTION 1 g');
        self::assertInstanceOf(AddOperatorFamilyMembersStatement::class, $statement);
        self::assertSame('hash', $statement->withMethod('hash')->method);
    }

    public function testWithMembersReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree ADD FUNCTION 1 g');
        self::assertInstanceOf(AddOperatorFamilyMembersStatement::class, $statement);
        self::assertSame('ALTER OPERATOR FAMILY "f" USING "btree" ADD FUNCTION 2 "h"', $statement->withMembers([new SupportFunctionMember(2, new RoutineByName(new QualifiedName(['h'])))])->toString());
    }
}
