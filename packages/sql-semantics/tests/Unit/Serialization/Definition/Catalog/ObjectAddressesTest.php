<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Catalog\ObjectAddresses;

#[CoversClass(ObjectAddresses::class)]
#[Medium]
final class ObjectAddressesTest extends TestCase
{
    #[TestWith([Kind\NamedObjectKind::Schema, 'app', 'SCHEMA "app"'])]
    #[TestWith([Kind\NamedObjectKind::ForeignDataWrapper, 'w', 'FOREIGN DATA WRAPPER "w"'])]
    public function testWriteSpellsTheClassAndIdentity(Kind\NamedObjectKind $kind, string $name, string $expected): void
    {
        self::assertSame($expected, ObjectAddresses::write(new Catalog\NamedIdentity($kind, $name))->toString());
    }

    public function testMemberWritesColumnsAsPathsAndOtherMembersWithTheirRelation(): void
    {
        self::assertSame('COLUMN "app"."t"."id"', implode(' ', array_map(static fn ($tree): string => $tree->toString(), ObjectAddresses::member(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'id', new QualifiedName(['app', 't']))))));
        self::assertSame('TRIGGER "tr" ON "t"', implode(' ', array_map(static fn ($tree): string => $tree->toString(), ObjectAddresses::member(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'tr', new QualifiedName(['t']))))));
    }

    public function testOperatorQualifiesTheSymbolWithoutQuotingIt(): void
    {
        self::assertSame('"app".+', ObjectAddresses::operator(new QualifiedName(['app', '+']))->toString());
        self::assertSame('<->', ObjectAddresses::operator(new QualifiedName(['<->']))->toString());
    }

    public function testOperandWritesNoneForAMissingSide(): void
    {
        self::assertSame('NONE', ObjectAddresses::operand(null)->toString());
        self::assertSame('integer', ObjectAddresses::operand(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))->toString());
    }

    #[TestWith(["COMMENT ON TABLE \"public\".\"t\" IS 'x'"])]
    #[TestWith(["COMMENT ON COLLATION \"s\".\"c\" IS 'x'"])]
    #[TestWith(["COMMENT ON CONSTRAINT \"c\" ON DOMAIN \"s\".\"d\" IS 'x'"])]
    #[TestWith(["COMMENT ON TYPE integer [] IS 'x'"])]
    #[TestWith(["COMMENT ON DOMAIN \"s\".\"d\" IS 'x'"])]
    #[TestWith(["COMMENT ON AGGREGATE \"s\".\"a\"(integer) IS 'x'"])]
    #[TestWith(["COMMENT ON FUNCTION \"s\".\"f\"(integer) IS 'x'"])]
    #[TestWith(["COMMENT ON OPERATOR \"s\".+ (integer, NONE) IS 'x'"])]
    #[TestWith(["COMMENT ON OPERATOR \"s\".- (NONE, integer) IS 'x'"])]
    #[TestWith(["COMMENT ON OPERATOR CLASS \"s\".\"c\" USING \"btree\" IS 'x'"])]
    #[TestWith(["COMMENT ON LARGE OBJECT 42 IS 'x'"])]
    #[TestWith(["COMMENT ON CAST(integer AS text) IS 'x'"])]
    #[TestWith(["COMMENT ON TRANSFORM FOR integer LANGUAGE \"plpgsql\" IS 'x'"])]
    #[TestWith(["COMMENT ON COLUMN \"t\".\"a\" IS 'x'"])]
    #[TestWith(["COMMENT ON TRIGGER \"tr\" ON \"t\" IS 'x'"])]
    public function testWriteSpellsEveryAddressForm(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a int)')))->bind($sql);
        self::assertInstanceOf(CommentOnStatement::class, $statement);
        self::assertSame(substr($sql, strlen('COMMENT ON '), -strlen(" IS 'x'")), ObjectAddresses::write($statement->object)->toString());
    }

    public function testWriteRejectsAnUnclassifiedAddress(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessageMatches('/^Unclassified catalog object address: SqlSemantics\\\\Model\\\\Definition\\\\ObjectAddress@anonymous/');
        ObjectAddresses::write(new class () implements ObjectAddress {
        });
    }
}
