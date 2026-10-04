<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\Joining;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Joining::class)]
#[Medium]
final class JoiningTest extends TestCase
{
    public function testJoinHidesTheRightUsingColumnAndKeepsTheLeftSide(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $shapes = new TableShapes();
        $left = new TableInput(new QualifiedName(new Name('t')));
        $right = new TableInput(new QualifiedName(new Name('u')));
        $leftVisible = new VisibleRelation($left, $shapes->fact($derivation, $left->name, $derivation->environment())->shape, null, $left->name);
        $rightVisible = new VisibleRelation($right, $shapes->fact($derivation, $right->name, $derivation->environment())->shape, null, $right->name);
        $step = new JoinStep(new JoinOperator(), $right, new JoinUsing([new Name('A')]));
        $joined = (new Joining())->join($derivation, [$leftVisible], [$rightVisible], $step);

        self::assertCount(2, $joined);
        self::assertSame($leftVisible, $joined[0]);
        self::assertSame($right, $joined[1]->relation);
        self::assertSame([0], $joined[1]->hidden);
        self::assertSame($rightVisible->shape, $joined[1]->shape);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testJoinMergesEveryCommonNameOfANaturalJoin(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $shapes = new TableShapes();
        $left = new TableInput(new QualifiedName(new Name('t')));
        $right = new TableInput(new QualifiedName(new Name('t')), new Name('t2'));
        $shape = $shapes->fact($derivation, $left->name, $derivation->environment())->shape;
        $step = new JoinStep(new JoinOperator(false, [JoinKeyword::Natural]), $right);
        $joined = (new Joining())->join($derivation, [new VisibleRelation($left, $shape, null, $left->name)], [new VisibleRelation($right, $shape, new Name('t2'), $right->name)], $step);

        self::assertSame([0, 1, 2], $joined[1]->hidden);
        self::assertSame([], $joined[0]->hidden);
    }

    public function testJoinExtendsTheRightSideOfALeftJoin(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $shapes = new TableShapes();
        $left = new TableInput(new QualifiedName(new Name('t')));
        $right = new TableInput(new QualifiedName(new Name('u')));
        $leftVisible = new VisibleRelation($left, $shapes->fact($derivation, $left->name, $derivation->environment())->shape, null, $left->name);
        $fact = $shapes->fact($derivation, $right->name, $derivation->environment());
        $rightVisible = new VisibleRelation($right, $fact->shape, null, $right->name, [], $shapes->implicit($fact));
        $step = new JoinStep(new JoinOperator(false, [JoinKeyword::Left, JoinKeyword::Outer]), $right);
        $joined = (new Joining())->join($derivation, [$leftVisible], [$rightVisible], $step);

        self::assertSame($leftVisible, $joined[0]);
        self::assertSame(Nullability::Nullable, $joined[1]->shape->slots[0]->nullability);
        self::assertSame($u->declarations()[0]->columns[0], $joined[1]->shape->slots[0]->declaration());
        self::assertSame($fact->shape->slots[0], $joined[1]->shape->slots[0]->origin);
        self::assertSame(Nullability::Nullable, $joined[1]->implicit[0]->slot->nullability);
    }

    public function testJoinCoalescesTheMergedColumnAndExtendsTheLeftSideOfARightJoin(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $shapes = new TableShapes();
        $left = new TableInput(new QualifiedName(new Name('t')));
        $right = new TableInput(new QualifiedName(new Name('u')));
        $leftShape = $shapes->fact($derivation, $left->name, $derivation->environment())->shape;
        $rightShape = $shapes->fact($derivation, $right->name, $derivation->environment())->shape;
        $step = new JoinStep(new JoinOperator(false, [JoinKeyword::Right]), $right, new JoinUsing([new Name('a')]));
        $joined = (new Joining())->join($derivation, [new VisibleRelation($left, $leftShape, null, $left->name)], [new VisibleRelation($right, $rightShape, null, $right->name)], $step);

        self::assertInstanceOf(Choice::class, $joined[0]->shape->slots[1]->type);
        self::assertSame($t->declarations()[0]->columns[1], $joined[0]->shape->slots[1]->declaration());
        self::assertSame(Nullability::Nullable, $joined[0]->shape->slots[0]->nullability);
        self::assertSame($rightShape, $joined[1]->shape);
        self::assertSame([0], $joined[1]->hidden);
        self::assertSame(Nullability::NotNull, $joined[1]->shape->slots[0]->nullability);
    }

    public function testJoinReportsAUsingColumnThatAClosedSideLacks(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t], false));
        $shapes = new TableShapes();
        $left = new TableInput(new QualifiedName(new Name('t')));
        $right = new TableInput(new QualifiedName(new Name('u')));
        $leftVisible = new VisibleRelation($left, $shapes->fact($derivation, $left->name, $derivation->environment())->shape, null, $left->name);
        $rightVisible = new VisibleRelation($right, $shapes->fact($derivation, $right->name, $derivation->environment())->shape, null, $right->name);
        $joining = new Joining();
        $joining->join($derivation, [$leftVisible], [$rightVisible], new JoinStep(new JoinOperator(), $right, new JoinUsing([new Name('zz')])));
        $joining->join($derivation, [$leftVisible], [$rightVisible], new JoinStep(new JoinOperator(), $right, new JoinUsing([new Name('a')])));
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(MissingColumn::class, $diagnostics[0]);
        self::assertSame('zz', $diagnostics[0]->name->value);
    }

    public function testReplacedSwapsOneRelationByPosition(): void
    {
        $input = new TableInput(new QualifiedName(new Name('t')));
        $first = new VisibleRelation($input, new RowShape([]));
        $second = new VisibleRelation($input, new RowShape([]), new Name('x'));
        $third = new VisibleRelation($input, new RowShape([]), new Name('y'));
        $replaced = (new Joining())->replaced([$first, $second], 1, $third);

        self::assertSame([$first, $third], $replaced);
        self::assertSame([$third, $second], (new Joining())->replaced([$first, $second], 0, $third));
    }

    public function testCoalescedWidensOneSlotAndLinksItsOrigin(): void
    {
        $input = new TableInput(new QualifiedName(new Name('t')));
        $kept = new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull);
        $other = new OutputSlot(new Name('b'), new Known(Storage::Text), Nullability::NotNull);
        $relation = new VisibleRelation($input, new RowShape([$kept, $other]), new Name('x'), $input->name, [1]);
        $coalesced = (new Joining())->coalesced($relation, 0, new OutputSlot(new Name('a'), new Known(Storage::Text), Nullability::Nullable));

        self::assertSame($input, $coalesced->relation);
        self::assertSame('x', $coalesced->alias?->value);
        self::assertSame([1], $coalesced->hidden);
        self::assertSame('a', $coalesced->shape->slots[0]->name?->value);
        self::assertInstanceOf(Choice::class, $coalesced->shape->slots[0]->type);
        self::assertSame([Storage::Integer, Storage::Text], $coalesced->shape->slots[0]->type->alternatives);
        self::assertSame(Nullability::Nullable, $coalesced->shape->slots[0]->nullability);
        self::assertSame($kept, $coalesced->shape->slots[0]->origin);
        self::assertSame($other, $coalesced->shape->slots[1]);
    }

    public function testLocateFindsTheFirstVisibleSlotByName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $shapes = new TableShapes();
        $left = new TableInput(new QualifiedName(new Name('t')));
        $right = new TableInput(new QualifiedName(new Name('u')));
        $leftShape = $shapes->fact($derivation, $left->name, $derivation->environment())->shape;
        $rightShape = $shapes->fact($derivation, $right->name, $derivation->environment())->shape;
        $relations = [new VisibleRelation($left, $leftShape, null, $left->name, [1]), new VisibleRelation($right, $rightShape, null, $right->name)];
        $joining = new Joining();

        self::assertSame([0, 0], $joining->locate($relations, 'ID', $derivation));
        self::assertSame([1, 0], $joining->locate($relations, 'a', $derivation));
        self::assertSame([1, 1], $joining->locate($relations, 'c', $derivation));
        self::assertNull($joining->locate($relations, 'zz', $derivation));
        self::assertNull($joining->locate([new VisibleRelation($left, $leftShape, new Name('j'), null, [ColumnResolver::QUALIFIED_ONLY])], 'id', $derivation));
    }

    public function testClosedIsTrueOnlyForCompleteFullyNamedShapes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t], false));
        $shapes = new TableShapes();
        $input = new TableInput(new QualifiedName(new Name('t')));
        $declared = new VisibleRelation($input, $shapes->fact($derivation, $input->name, $derivation->environment())->shape);
        $open = new VisibleRelation($input, $shapes->fact($derivation, new QualifiedName(new Name('u')), $derivation->environment())->shape);
        $unnamed = new VisibleRelation($input, new RowShape([new OutputSlot(null, new Known(Storage::Integer), Nullability::NotNull)]));
        $joining = new Joining();

        self::assertTrue($joining->closed([$declared]));
        self::assertTrue($joining->closed([]));
        self::assertFalse($joining->closed([$declared, $open]));
        self::assertFalse($joining->closed([$unnamed]));
    }

    public function testExtendMakesEverySlotAndImplicitNullableAndKeepsOrigins(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $shapes = new TableShapes();
        $input = new TableInput(new QualifiedName(new Name('t')), new Name('x'));
        $fact = $shapes->fact($derivation, $input->name, $derivation->environment());
        $relation = new VisibleRelation($input, $fact->shape, $input->alias, $input->name, [2], $shapes->implicit($fact));
        $extended = (new Joining())->extend([$relation]);

        self::assertCount(1, $extended);
        self::assertSame($input, $extended[0]->relation);
        self::assertSame('x', $extended[0]->alias?->value);
        self::assertSame([2], $extended[0]->hidden);
        self::assertSame([Nullability::Nullable, Nullability::Nullable, Nullability::Nullable], array_map(static fn (OutputSlot $slot): Nullability => $slot->nullability, $extended[0]->shape->slots));
        self::assertSame($fact->shape->slots[0], $extended[0]->shape->slots[0]->origin);
        self::assertSame($fact->shape->slots[2], $extended[0]->shape->slots[2]);
        self::assertSame($t->declarations()[0]->columns[1], $extended[0]->shape->slots[1]->declaration());
        self::assertSame(['rowid', 'oid', '_rowid_'], array_map(static fn (Name $name): string => $name->value, $extended[0]->implicit[0]->names));
        self::assertSame(Nullability::Nullable, $extended[0]->implicit[0]->slot->nullability);
        self::assertSame($t->declarations()[0]->columns[0], $extended[0]->implicit[0]->slot->declaration());
    }
}
