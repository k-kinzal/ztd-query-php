<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Characteristics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\AlterationInvariant;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineAlteration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterationInvariant::class)]
#[Medium]
final class AlterationInvariantTest extends TestCase
{
    public function testTargetRejectsAnEmptyNameComponent(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AlterationInvariant::target($origin, new QualifiedName(['db', '']));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    public function testChangesRejectsANamedLanguageInTheSqlOnlyGrammar(string $version): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AlterationInvariant::changes($origin, new RoutineAlteration(language: 'JavaScript'));
    }

    public function testTargetAcceptsADatabaseQualifiedName(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectNotToPerformAssertions();
        AlterationInvariant::target($origin, new QualifiedName(['db', 'f']));
    }

    public function testTargetRejectsAThreePartName(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AlterationInvariant::target($origin, new QualifiedName(['c', 'db', 'f']));
    }

    public function testTargetRejectsAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        AlterationInvariant::target($origin, new QualifiedName(['f']));
    }

    #[TestWith(['mysql-8.4.7', 'JavaScript'])]
    #[TestWith(['mysql-9.1.0', 'JavaScript'])]
    #[TestWith(['mysql-5.7.44', 'sql'])]
    public function testChangesAcceptsALanguageTheGrammarDeclares(string $version, string $language): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        $this->expectNotToPerformAssertions();
        AlterationInvariant::changes($origin, new RoutineAlteration(language: $language));
    }
}
