<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
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
}
