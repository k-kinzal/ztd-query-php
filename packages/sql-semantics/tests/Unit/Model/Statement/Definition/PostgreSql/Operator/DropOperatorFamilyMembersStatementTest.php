<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\MemberKind;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\MemberRemoval;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\DropOperatorFamilyMembersStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DropOperatorFamilyMembersStatement::class)]
#[Medium]
final class DropOperatorFamilyMembersStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (integer), FUNCTION 2 (integer, text)');
        self::assertInstanceOf(DropOperatorFamilyMembersStatement::class, $statement);
        self::assertSame(MemberKind::Function, $statement->members[1]->kind);
        self::assertSame('integer', $statement->members[0]->right->name);
        self::assertSame('ALTER OPERATOR FAMILY "f" USING "btree" DROP OPERATOR 1(integer, integer), FUNCTION 2(integer, text)', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (integer)');
        self::assertInstanceOf(DropOperatorFamilyMembersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (integer)');
        self::assertInstanceOf(DropOperatorFamilyMembersStatement::class, $statement);
        self::assertSame('ALTER OPERATOR FAMILY "f" USING "btree" DROP OPERATOR 1(integer, integer)', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithFamilyReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (integer)');
        self::assertInstanceOf(DropOperatorFamilyMembersStatement::class, $statement);
        self::assertSame(['g'], $statement->withFamily(new QualifiedName(['g']))->family->parts);
    }

    public function testWithMethodReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (integer)');
        self::assertInstanceOf(DropOperatorFamilyMembersStatement::class, $statement);
        self::assertSame('ALTER OPERATOR FAMILY "f" USING "hash" DROP OPERATOR 1(integer, integer)', $statement->withMethod('hash')->toString());
    }

    public function testWithMembersReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (integer)');
        self::assertInstanceOf(DropOperatorFamilyMembersStatement::class, $statement);
        $removal = new MemberRemoval(MemberKind::Function, 3, TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
        self::assertSame(3, $statement->withMembers([$removal])->members[0]->number);
        self::assertSame(1, $statement->members[0]->number);
    }
}
