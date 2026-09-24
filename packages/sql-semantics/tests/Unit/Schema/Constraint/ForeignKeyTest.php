<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\Schema\Constraint\MatchMode;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\Schema\ReferentialAction;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ForeignKey::class)]
#[Medium]
final class ForeignKeyTest extends TestCase
{
    public function testLocalColumnsAreTheReferencingColumns(): void
    {
        $key = new ForeignKey(['a', 'b'], new QualifiedName(['p']), ['x', 'y'], name: 'fk');
        self::assertSame(['a', 'b'], $key->localColumns());
        self::assertSame(ConstraintKind::ForeignKey, $key->kind);
        self::assertSame(ReferentialAction::NoAction, $key->onDelete);
        self::assertSame(MatchMode::Simple, $key->match);
        self::assertSame(CheckingTime::Immediate, $key->checking);
    }

    public function testRejectsAnEmptyKey(): void
    {
        $this->expectException(InvalidStructure::class);
        new ForeignKey([], new QualifiedName(['p']));
    }

    public function testRejectsMismatchedKeyWidths(): void
    {
        $this->expectException(InvalidStructure::class);
        new ForeignKey(['a', 'b'], new QualifiedName(['p']), ['x']);
    }

    public function testRejectsAffectedColumnsWithoutASetAction(): void
    {
        $this->expectException(InvalidStructure::class);
        new ForeignKey(['a'], new QualifiedName(['p']), deleteColumns: ['a']);
    }

    public function testBindsEveryReferentialClause(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER PRIMARY KEY); CREATE TABLE c(pid INTEGER, CONSTRAINT fk FOREIGN KEY (pid) REFERENCES p(id) MATCH FULL ON DELETE SET NULL (pid) ON UPDATE CASCADE DEFERRABLE INITIALLY DEFERRED)')->tables[1];
        $key = $table->constraints[0];
        self::assertInstanceOf(ForeignKey::class, $key);
        self::assertSame('fk', $key->name);
        self::assertSame(['pid'], $key->columns);
        self::assertSame(['p'], $key->referencedTable->parts);
        self::assertSame(['id'], $key->referencedColumns);
        self::assertSame(ReferentialAction::SetNull, $key->onDelete);
        self::assertSame(ReferentialAction::Cascade, $key->onUpdate);
        self::assertSame(MatchMode::Full, $key->match);
        self::assertSame(CheckingTime::DeferrableDeferred, $key->checking);
        self::assertSame(['pid'], $key->deleteColumns);
    }
}
