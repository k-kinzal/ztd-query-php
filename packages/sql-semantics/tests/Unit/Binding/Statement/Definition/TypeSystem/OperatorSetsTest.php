<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\OperatorSets;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Catalog\Kind\OperatorSetKind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OperatorSets::class)]
#[Medium]
final class OperatorSetsTest extends TestCase
{
    public function testCreateFamilyReadsTheNameAndMethod(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR FAMILY s.f USING hash');
        self::assertInstanceOf(Statement\CreateOperatorFamilyStatement::class, $statement);
        self::assertSame(['s', 'f'], $statement->name->parts);
        self::assertSame('hash', $statement->method);
    }

    public function testCreateClassReadsTheDefaultFamilyAndMembers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS c DEFAULT FOR TYPE text USING hash FAMILY s.f AS OPERATOR 1 =, FUNCTION 1 hashtext(text)');
        self::assertInstanceOf(Statement\CreateOperatorClassStatement::class, $statement);
        self::assertTrue($statement->isDefault);
        self::assertSame(['s', 'f'], $statement->family?->parts);
        self::assertCount(2, $statement->members);
    }

    public function testAlterFamilyDistinguishesAddingFromDropping(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertInstanceOf(Statement\AddOperatorFamilyMembersStatement::class, $binder->bind('ALTER OPERATOR FAMILY f USING btree ADD OPERATOR 1 < (integer, bigint)'));
        self::assertInstanceOf(Statement\DropOperatorFamilyMembersStatement::class, $binder->bind('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (integer, bigint)'));
    }

    #[TestWith(['CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS STORAGE text, STORAGE text'])]
    #[TestWith(['CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 0 <'])]
    #[TestWith(['CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 40000 <'])]
    #[TestWith(['CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 < (integer, NONE)'])]
    #[TestWith(['CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS FUNCTION 1 (integer, integer, integer) f'])]
    #[TestWith(['ALTER OPERATOR FAMILY f USING btree ADD OPERATOR 1 <'])]
    #[TestWith(['ALTER OPERATOR FAMILY f USING btree ADD STORAGE text'])]
    #[TestWith(['ALTER OPERATOR FAMILY f USING btree DROP FUNCTION 1 (integer, integer, integer)'])]
    public function testMemberDiagnosesImpossibleMembers(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::OperatorClassMember->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testRemovalNormalizesOneTypeToBoth(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree DROP FUNCTION 1 (text)');
        self::assertInstanceOf(Statement\DropOperatorFamilyMembersStatement::class, $statement);
        self::assertSame('text', $statement->members[0]->left->name);
        self::assertSame('text', $statement->members[0]->right->name);
    }

    public function testTypesIsEmptyWithoutAList(): void
    {
        self::assertSame([null, null], OperatorSets::types(null));
    }

    public function testNumberReadsLeadingZeros(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 0007 (text)');
        self::assertInstanceOf(Statement\DropOperatorFamilyMembersStatement::class, $statement);
        self::assertSame(7, $statement->members[0]->number);
    }

    public function testDropDistinguishesClassesFromFamilies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $class = $binder->bind('DROP OPERATOR CLASS IF EXISTS c USING btree CASCADE');
        self::assertInstanceOf(Statement\DropOperatorSetStatement::class, $class);
        self::assertSame(OperatorSetKind::OperatorClass, $class->object->kind);
        self::assertTrue($class->ifExists);
        self::assertSame(DropBehavior::Cascade, $class->behavior);
        $family = $binder->bind('DROP OPERATOR FAMILY f USING btree');
        self::assertInstanceOf(Statement\DropOperatorSetStatement::class, $family);
        self::assertSame(OperatorSetKind::OperatorFamily, $family->object->kind);
        self::assertFalse($family->ifExists);
    }

    public function testNameRejectsAnOverQualifiedName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR FAMILY a.b.c USING btree');
    }

    public function testMethodReadsTheAccessMethod(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR FAMILY f USING "My AM"');
        self::assertInstanceOf(Statement\DropOperatorSetStatement::class, $statement);
        self::assertSame('My AM', $statement->object->method);
    }
}
