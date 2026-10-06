<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TableShapes::class)]
#[Medium]
final class TableShapesTest extends TestCase
{
    public function testFactAnswersTheDeclaredShapeWithItsColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $fact = (new TableShapes())->fact($derivation, new QualifiedName(new Name('T')), $derivation->environment());

        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($t->declarations()[0], $fact->table->table);
        self::assertTrue($fact->shape->complete());
        self::assertSame(['id', 'a', 'b'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertSame($t->declarations()[0]->columns[1], $fact->shape->slots[1]->column);
        self::assertInstanceOf(Known::class, $fact->shape->slots[1]->type);
        self::assertSame($t->declarations()[0]->columns[1]->type, $fact->shape->slots[1]->type->descriptor);
        self::assertSame(Nullability::NotNull, $fact->shape->slots[1]->nullability);
        self::assertSame(Nullability::Nullable, $fact->shape->slots[2]->nullability);
    }

    public function testFactCopiesTheShapeOfACommonTableAndLinksItsSlots(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $with = $semantics->analyze('WITH w AS (SELECT a FROM t) SELECT 1', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));
        $slot = new OutputSlot(new Name('q'), new Known(Storage::Integer), Nullability::NotNull);

        self::assertInstanceOf(WithQuery::class, $with);
        $definition = $with->with->tables[0];
        $environment = new Environment($derivation->context, null, [], [new CommonBinding(new Name('w'), $definition, new RowShape([$slot], [new UndeclaredRelation(new QualifiedName(new Name('z')))]))]);
        $fact = (new TableShapes())->fact($derivation, new QualifiedName(new Name('w')), $environment);
        self::assertInstanceOf(CommonTable::class, $fact->table);
        self::assertSame($definition, $fact->table->definition);
        self::assertCount(1, $fact->shape->slots);
        self::assertNotSame($slot, $fact->shape->slots[0]);
        self::assertSame($slot, $fact->shape->slots[0]->origin);
        self::assertSame('q', $fact->shape->slots[0]->name?->value);
        self::assertFalse($fact->shape->complete());
        self::assertInstanceOf(MissingTable::class, (new TableShapes())->fact($derivation, new QualifiedName(new Name('w'), new Name('main')), $environment)->table);
    }

    public function testFactAnswersAnOpenShapeForAnUndeclaredTableAndAnEmptyOneForAMissingTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $open = new Derivation($semantics->context());
        $closed = new Derivation($semantics->context([]));
        $shapes = new TableShapes();
        $undeclared = $shapes->fact($open, new QualifiedName(new Name('u')), $open->environment());
        $missing = $shapes->fact($closed, new QualifiedName(new Name('u')), $closed->environment());

        self::assertInstanceOf(UndeclaredTable::class, $undeclared->table);
        self::assertSame([], $undeclared->shape->slots);
        self::assertSame([$undeclared->table->missing], $undeclared->shape->missing);
        self::assertInstanceOf(MissingTable::class, $missing->table);
        self::assertSame('u', $missing->table->name->name->value);
        self::assertSame([], $missing->shape->slots);
        self::assertTrue($missing->shape->complete());
    }

    public function testFactMarksAnIncompleteDeclarationAsOpen(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = new Table(new QualifiedName(new Name('t')), $semantics->profile(), [new Column(new Name('a'), new ColumnDomain('INTEGER'))], [], false);
        $derivation = new Derivation($semantics->context([$table]));
        $fact = (new TableShapes())->fact($derivation, new QualifiedName(new Name('t')), $derivation->environment());

        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertCount(1, $fact->shape->slots);
        self::assertInstanceOf(IncompleteMembers::class, $fact->shape->missing[0]);
        self::assertSame($table, $fact->shape->missing[0]->table);
    }

    public function testImplicitListsTheRowIdentifierOfADeclaredTableOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $plain = $semantics->analyze('CREATE TABLE p (a)');
        $without = $semantics->analyze('CREATE TABLE w (a PRIMARY KEY) WITHOUT ROWID');
        $derivation = new Derivation($semantics->context([$t, $plain, $without]));
        $shapes = new TableShapes();
        $aliased = $shapes->implicit($shapes->fact($derivation, new QualifiedName(new Name('t')), $derivation->environment()));
        $hidden = $shapes->implicit($shapes->fact($derivation, new QualifiedName(new Name('p')), $derivation->environment()));

        self::assertCount(1, $aliased);
        self::assertSame(['rowid', 'oid', '_rowid_'], array_map(static fn (Name $name): string => $name->value, $aliased[0]->names));
        self::assertSame($t->declarations()[0]->columns[0], $aliased[0]->slot->column);
        self::assertSame(Nullability::NotNull, $aliased[0]->slot->nullability);
        self::assertSame($plain->declarations()[0]->implicit[0]->column, $hidden[0]->slot->column);
        self::assertSame([], $shapes->implicit($shapes->fact($derivation, new QualifiedName(new Name('w')), $derivation->environment())));
        self::assertSame([], $shapes->implicit($shapes->fact($derivation, new QualifiedName(new Name('zz')), $derivation->environment())));
    }
}
