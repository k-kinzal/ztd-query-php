<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Extension\ExtensionInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExtensionInvariant::class)]
#[Medium]
final class ExtensionInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDialectRejectsAnotherDatabaseLanguage(Dialect $dialect): void
    {
        ExtensionInvariant::dialect((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin);
        $this->expectException(InvalidStructure::class);
        ExtensionInvariant::dialect((new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin);
    }

    public function testNamesAcceptsOmittedSettings(): void
    {
        ExtensionInvariant::names('hstore', null, '1.0');
        $this->expectException(InvalidStructure::class);
        ExtensionInvariant::names('hstore', '');
    }

    public function testNamesRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        ExtensionInvariant::names('');
    }

    public function testFunctionAllowsThreeComponents(): void
    {
        ExtensionInvariant::function(null);
        ExtensionInvariant::function(new QualifiedName(['db', 'app', 'f']));
        $this->expectException(InvalidStructure::class);
        ExtensionInvariant::function(new QualifiedName(['a', 'b', 'c', 'd']));
    }

    public function testMemberAcceptsWholeObjects(): void
    {
        ExtensionInvariant::member(new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t'])));
        ExtensionInvariant::member(new Catalog\NamedIdentity(Kind\NamedObjectKind::Language, 'plpgsql'));
        $this->expectException(InvalidStructure::class);
        ExtensionInvariant::member(new Catalog\LargeObjectIdentity(5));
    }

    public function testMemberRejectsARelationMember(): void
    {
        $this->expectException(InvalidStructure::class);
        ExtensionInvariant::member(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'tr', new QualifiedName(['t'])));
    }
}
