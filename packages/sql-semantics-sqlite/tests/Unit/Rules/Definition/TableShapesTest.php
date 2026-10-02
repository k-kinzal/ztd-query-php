<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Resolution\ColumnLookup;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(TableShapes::class)]
#[Medium]
final class TableShapesTest extends TestCase
{
    public function testShapeHasOneSlotPerDeclaredColumn(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER NOT NULL, b)')->declarations()[0];
        $shape = (new TableShapes())->shape($table);

        self::assertCount(2, $shape->slots);
        self::assertSame($table->columns[0], $shape->slots[0]->column);
        self::assertSame($table->columns[0]->nullability, $shape->slots[0]->nullability);
        self::assertTrue($shape->complete());
    }

    public function testShapeOfAnIncompleteDeclarationIsOpen(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = new Table(new QualifiedName(new Name('t')), $semantics->profile(), [new Column(new Name('a'), new ColumnDomain(''))], [], false);
        $shape = (new TableShapes())->shape($table);

        self::assertFalse($shape->complete());
        self::assertInstanceOf(IncompleteMembers::class, $shape->missing[0]);
    }

    public function testImplicitPointsAnAliasAtTheSlotOfItsDeclaredColumn(): void
    {
        $shapes = new TableShapes();
        $semantics = new Semantics(Dialect::Sqlite);
        $aliased = $semantics->analyze('CREATE TABLE t (a, id INTEGER PRIMARY KEY)')->declarations()[0];
        $plain = $semantics->analyze('CREATE TABLE t (a)')->declarations()[0];
        $shape = $shapes->shape($aliased);

        self::assertSame($shape->slots[1], $shapes->implicit($aliased, $shape)[0]->slot);
        self::assertSame($plain->implicit[0]->column, $shapes->implicit($plain, $shapes->shape($plain))[0]->slot->column);
        self::assertSame([], $shapes->implicit($semantics->analyze('CREATE TABLE t (a PRIMARY KEY) WITHOUT ROWID')->declarations()[0], $shape));
    }

    public function testTargetResolvesADeclaredAMissingAndAnUndeclaredTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a, b)');
        $shapes = new TableShapes();
        $declared = $shapes->target(new Derivation($semantics->context([$table])), new QualifiedName(new Name('T')));
        $missing = $shapes->target(new Derivation($semantics->context([$table])), new QualifiedName(new Name('u')));
        $undeclared = $shapes->target(new Derivation($semantics->context()), new QualifiedName(new Name('u')));

        self::assertInstanceOf(DeclaredTable::class, $declared->table);
        self::assertCount(2, $declared->shape->slots);
        self::assertInstanceOf(MissingTable::class, $missing->table);
        self::assertTrue($missing->shape->complete());
        self::assertInstanceOf(UndeclaredTable::class, $undeclared->table);
        self::assertFalse($undeclared->shape->complete());
    }

    public function testImplicitOfAnswersTheRowIdentifierOfAResolvedTableOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a)');
        $shapes = new TableShapes();

        self::assertCount(1, $shapes->implicitOf($shapes->target(new Derivation($semantics->context([$table])), new QualifiedName(new Name('t')))));
        self::assertSame([], $shapes->implicitOf($shapes->target(new Derivation($semantics->context()), new QualifiedName(new Name('t')))));
    }

    public function testScopeSeesTheRowIdentifierOnlyAtTheRowPosition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a)');
        $index = $semantics->analyze('CREATE INDEX i ON t (a)')->statement;
        $derivation = new Derivation($semantics->context([$table]));
        $shapes = new TableShapes();
        $fact = $shapes->target($derivation, new QualifiedName(new Name('t')));

        self::assertInstanceOf(CreateIndex::class, $index);
        $scope = $shapes->scope($derivation, $index, new QualifiedName(new Name('t')), $fact->shape, $shapes->implicitOf($fact));
        self::assertInstanceOf(ResolvedColumn::class, (new ColumnLookup())->find($scope->row, new Name('rowid')));
        self::assertNotInstanceOf(ResolvedColumn::class, (new ColumnLookup())->find($scope->columns, new Name('rowid')));
        self::assertInstanceOf(ResolvedColumn::class, (new ColumnLookup())->find($scope->columns, new Name('a')));
        self::assertNotInstanceOf(ResolvedColumn::class, (new ColumnLookup())->find($scope->constant, new Name('a')));
        self::assertFalse($scope->lacks(new Name('A')));
        self::assertTrue($scope->lacks(new Name('rowid')));
    }
}
