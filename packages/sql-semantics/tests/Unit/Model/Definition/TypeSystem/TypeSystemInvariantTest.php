<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(TypeSystemInvariant::class)]
#[Medium]
final class TypeSystemInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDialectRejectsAnotherDatabaseLanguage(Dialect $dialect): void
    {
        TypeSystemInvariant::dialect((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin);
        $this->expectException(InvalidStructure::class);
        TypeSystemInvariant::dialect((new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin);
    }

    public function testNameRejectsAnOverQualifiedName(): void
    {
        TypeSystemInvariant::name(new QualifiedName(['app', 'money']));
        $this->expectException(InvalidStructure::class);
        TypeSystemInvariant::name(new QualifiedName(['db', 'app', 'money']));
    }

    public function testIdentifierRejectsAnEmptyName(): void
    {
        TypeSystemInvariant::identifier('a');
        $this->expectException(InvalidStructure::class);
        TypeSystemInvariant::identifier('');
    }

    public function testTypeRejectsAnotherDatabaseLanguage(): void
    {
        TypeSystemInvariant::type(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'));
        $this->expectException(InvalidStructure::class);
        TypeSystemInvariant::type(TypeDescriptor::builtin(Dialect::MySql, 'integer'));
    }
}
