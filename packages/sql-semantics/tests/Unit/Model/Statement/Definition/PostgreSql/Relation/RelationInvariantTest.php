<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RelationInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RelationInvariant::class)]
#[Medium]
final class RelationInvariantTest extends TestCase
{
    public function testDialectRejectsAnotherDatabaseLanguage(): void
    {
        RelationInvariant::dialect((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin);
        $this->expectException(InvalidStructure::class);
        RelationInvariant::dialect((new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin);
    }

    public function testTargetRejectsOnlyForAView(): void
    {
        RelationInvariant::target(RelationKind::ForeignTable, new QualifiedName(['f']), true);
        $this->expectException(InvalidStructure::class);
        RelationInvariant::target(RelationKind::View, new QualifiedName(['v']), true);
    }

    public function testKindRejectsAnUnlistedClass(): void
    {
        RelationInvariant::kind(RelationKind::Table, [RelationKind::Table]);
        $this->expectException(InvalidStructure::class);
        RelationInvariant::kind(RelationKind::Index, [RelationKind::Table]);
    }
}
