<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\OperatorMember;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\StorageMember;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\SupportFunctionMember;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\CreateOperatorClassStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateOperatorClassStatement::class)]
#[Medium]
final class CreateOperatorClassStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE OPERATOR CLASS s.c FOR TYPE integer USING gist AS OPERATOR 1 s.<-> (integer, integer) FOR ORDER BY s.f RECHECK, FUNCTION 2 (integer) g(integer), STORAGE text');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertInstanceOf(OperatorMember::class, $statement->members[0]);
        self::assertSame(['s', 'f'], $statement->members[0]->orderFamily?->parts);
        self::assertInstanceOf(SupportFunctionMember::class, $statement->members[1]);
        self::assertSame('integer', $statement->members[1]->right?->name);
        self::assertFalse($statement->isDefault);
        self::assertNull($statement->family);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame('CREATE OPERATOR CLASS "s"."c" FOR TYPE integer USING "gist" AS OPERATOR 1 "s".<-> (integer, integer) FOR ORDER BY "s"."f", FUNCTION 2(integer, integer) "g"(integer), STORAGE text', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsTwoStorageTypes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        $storage = new StorageMember(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
        $this->expectException(InvalidStructure::class);
        $statement->withMembers([$storage, $storage]);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertSame('CREATE OPERATOR CLASS "c" FOR TYPE integer USING "btree" AS OPERATOR 1 <', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertSame(['d'], $statement->withName(new QualifiedName(['d']))->name->parts);
        self::assertSame(['c'], $statement->name->parts);
    }

    public function testWithTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertSame('bigint', $statement->withType(TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'))->type->name);
    }

    public function testWithMethodReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertSame('hash', $statement->withMethod('hash')->method);
    }

    public function testWithMembersReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertSame('CREATE OPERATOR CLASS "c" FOR TYPE integer USING "btree" AS OPERATOR 2 <=', $statement->withMembers([new OperatorMember(2, new QualifiedName(['<=']))])->toString());
    }

    public function testWithIsDefaultReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertSame('CREATE OPERATOR CLASS "c" DEFAULT FOR TYPE integer USING "btree" AS OPERATOR 1 <', $statement->withIsDefault(true)->toString());
    }

    public function testWithFamilyReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <');
        self::assertInstanceOf(CreateOperatorClassStatement::class, $statement);
        self::assertSame('CREATE OPERATOR CLASS "c" FOR TYPE integer USING "btree" FAMILY "f" AS OPERATOR 1 <', $statement->withFamily(new QualifiedName(['f']))->toString());
    }
}
