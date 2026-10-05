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
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\RandomColumnName;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnResolver::class)]
#[Medium]
final class ColumnResolverTest extends TestCase
{
    public function testFindResolvesUnqualifiedQualifiedAndImplicitNames(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $input = new TableInput(new QualifiedName(new Name('t')), new Name('x'));
        $fact = (new TableShapes())->fact($derivation, $input->name, $derivation->environment());
        $environment = new Environment($derivation->context, null, [new VisibleRelation($input, $fact->shape, $input->alias, $input->name, [], (new TableShapes())->implicit($fact))]);
        $resolver = new ColumnResolver();
        $bare = $resolver->find($environment, new Name('A'));
        $qualified = $resolver->find($environment, new Name('b'), new QualifiedName(new Name('x')));
        $implicit = $resolver->find($environment, new Name('oid'));
        $original = $resolver->find($environment, new Name('b'), new QualifiedName(new Name('t')));

        self::assertInstanceOf(ResolvedColumn::class, $bare);
        self::assertSame($input, $bare->relation);
        self::assertSame($fact->shape->slots[1], $bare->slot);
        self::assertSame(0, $bare->depth);
        self::assertInstanceOf(ResolvedColumn::class, $qualified);
        self::assertSame($fact->shape->slots[2], $qualified->slot);
        self::assertInstanceOf(ResolvedColumn::class, $implicit);
        self::assertSame($t->declarations()[0]->columns[0], $implicit->declaration());
        self::assertInstanceOf(MissingColumn::class, $original);
        self::assertSame('t', $original->qualifier?->name->value);
    }

    public function testFindReportsAmbiguityAndHonoursHiddenAndQualifiedOnlyRelations(): void
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
        $resolver = new ColumnResolver();
        $both = new Environment($derivation->context, null, [new VisibleRelation($left, $leftShape, null, $left->name), new VisibleRelation($right, $rightShape, null, $right->name)]);
        $merged = new Environment($derivation->context, null, [new VisibleRelation($left, $leftShape, null, $left->name), new VisibleRelation($right, $rightShape, null, $right->name, [0])]);
        $excluded = new Environment($derivation->context, null, [new VisibleRelation($left, $leftShape, null, $left->name), new VisibleRelation($left, $leftShape, new Name('excluded'), null, [ColumnResolver::QUALIFIED_ONLY])]);
        $ambiguous = $resolver->find($both, new Name('a'));
        $hidden = $resolver->find($merged, new Name('a'));
        $reached = $resolver->find($merged, new Name('a'), new QualifiedName(new Name('u')));
        $bare = $resolver->find($excluded, new Name('a'));
        $qualified = $resolver->find($excluded, new Name('a'), new QualifiedName(new Name('excluded')));

        self::assertInstanceOf(AmbiguousColumn::class, $ambiguous);
        self::assertCount(2, $ambiguous->candidates);
        self::assertInstanceOf(ResolvedColumn::class, $hidden);
        self::assertSame($left, $hidden->relation);
        self::assertInstanceOf(ResolvedColumn::class, $reached);
        self::assertSame($right, $reached->relation);
        self::assertInstanceOf(ResolvedColumn::class, $bare);
        self::assertSame($leftShape->slots[1], $bare->slot);
        self::assertInstanceOf(ResolvedColumn::class, $qualified);
        self::assertInstanceOf(MissingColumn::class, $resolver->find($both, new Name('zz')));
    }

    public function testFindPrefersARelationColumnOverAnAliasAndAnAliasOverOuterScopes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $shapes = new TableShapes();
        $outerInput = new TableInput(new QualifiedName(new Name('t')));
        $innerInput = new TableInput(new QualifiedName(new Name('u')));
        $outer = new Environment($derivation->context, null, [new VisibleRelation($outerInput, $shapes->fact($derivation, $outerInput->name, $derivation->environment())->shape, null, $outerInput->name)]);
        $alias = new Field(0, new OutputSlot(new Name('b'), new Known(Storage::Integer), Nullability::NotNull));
        $inner = new Environment($derivation->context, $outer, [new VisibleRelation($innerInput, $shapes->fact($derivation, $innerInput->name, $derivation->environment())->shape, null, $innerInput->name)], [], [$alias, new Field(1, new OutputSlot(new Name('c'), new Known(Storage::Text), Nullability::Nullable))]);
        $resolver = new ColumnResolver();
        $column = $resolver->find($inner, new Name('c'));
        $aliased = $resolver->find($inner, new Name('b'));
        $correlated = $resolver->find($inner, new Name('id'));
        $qualified = $resolver->find($inner, new Name('b'), new QualifiedName(new Name('t')));

        self::assertInstanceOf(ResolvedColumn::class, $column);
        self::assertSame($innerInput, $column->relation);
        self::assertInstanceOf(AliasTarget::class, $aliased);
        self::assertSame($alias, $aliased->field);
        self::assertInstanceOf(ResolvedColumn::class, $correlated);
        self::assertSame($outerInput, $correlated->relation);
        self::assertSame(1, $correlated->depth);
        self::assertInstanceOf(ResolvedColumn::class, $qualified);
        self::assertSame(1, $qualified->depth);
    }

    public function testFindIsConditionalWhenAnOpenRelationMayHoldTheName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t], false));
        $shapes = new TableShapes();
        $declared = new TableInput(new QualifiedName(new Name('t'), new Name('main')));
        $undeclared = new TableInput(new QualifiedName(new Name('u')));
        $environment = new Environment($derivation->context, null, [
            new VisibleRelation($declared, $shapes->fact($derivation, $declared->name, $derivation->environment())->shape, null, $declared->name),
            new VisibleRelation($undeclared, $shapes->fact($derivation, $undeclared->name, $derivation->environment())->shape, null, $undeclared->name),
        ]);
        $resolver = new ColumnResolver();
        $maybe = $resolver->find($environment, new Name('a'));
        $unknown = $resolver->find($environment, new Name('zz'));
        $certain = $resolver->find($environment, new Name('a'), new QualifiedName(new Name('t')));

        self::assertInstanceOf(ConditionalColumn::class, $maybe);
        self::assertCount(1, $maybe->candidates);
        self::assertSame([$undeclared], $maybe->relations);
        self::assertInstanceOf(UndeclaredRelation::class, $maybe->missing[0]);
        self::assertInstanceOf(ConditionalColumn::class, $unknown);
        self::assertSame([], $unknown->candidates);
        self::assertInstanceOf(ResolvedColumn::class, $certain);
    }

    public function testAdmitsMatchesTheAliasOrTheNameWithAnOptionalSchema(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context());
        $environment = $derivation->environment();
        $input = new TableInput(new QualifiedName(new Name('t'), new Name('main')));
        $plain = new VisibleRelation($input, new RowShape([]), null, $input->name);
        $aliased = new VisibleRelation($input, new RowShape([]), new Name('x'), $input->name);
        $unnamed = new VisibleRelation($input, new RowShape([]), null, null);
        $qualifiedOnly = new VisibleRelation($input, new RowShape([]), new Name('excluded'), null, [ColumnResolver::QUALIFIED_ONLY]);
        $resolver = new ColumnResolver();

        self::assertTrue($resolver->admits($environment, $plain, null));
        self::assertTrue($resolver->admits($environment, $plain, new QualifiedName(new Name('T'))));
        self::assertTrue($resolver->admits($environment, $plain, new QualifiedName(new Name('t'), new Name('MAIN'))));
        self::assertFalse($resolver->admits($environment, $plain, new QualifiedName(new Name('t'), new Name('temp'))));
        self::assertFalse($resolver->admits($environment, $plain, new QualifiedName(new Name('x'))));
        self::assertTrue($resolver->admits($environment, $aliased, new QualifiedName(new Name('x'))));
        self::assertFalse($resolver->admits($environment, $aliased, new QualifiedName(new Name('t'))));
        self::assertTrue($resolver->admits($environment, $unnamed, null));
        self::assertFalse($resolver->admits($environment, $unnamed, new QualifiedName(new Name('t'))));
        self::assertFalse($resolver->admits($environment, $qualifiedOnly, null));
        self::assertTrue($resolver->admits($environment, $qualifiedOnly, new QualifiedName(new Name('excluded'))));
        self::assertFalse($resolver->admits($environment, $qualifiedOnly, new QualifiedName(new Name('excluded'), new Name('main'))));
    }

    public function testMatchFindsSlotsOrImplicitNamesAndOtherwiseAnswersTheMissingInputs(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t], false));
        $environment = $derivation->environment();
        $shapes = new TableShapes();
        $input = new TableInput(new QualifiedName(new Name('t'), new Name('main')));
        $fact = $shapes->fact($derivation, $input->name, $environment);
        $visible = new VisibleRelation($input, $fact->shape, null, $input->name, [1], $shapes->implicit($fact));
        $open = new VisibleRelation(new TableInput(new QualifiedName(new Name('u'))), $shapes->fact($derivation, new QualifiedName(new Name('u')), $environment)->shape);
        $unnamed = new VisibleRelation($input, new RowShape([new OutputSlot(null, new Known(Storage::Integer), Nullability::NotNull)]));
        $resolver = new ColumnResolver();
        $slot = $resolver->match($environment, $visible, new Name('B'), true, 2);
        $hidden = $resolver->match($environment, $visible, new Name('a'), true, 0);
        $reached = $resolver->match($environment, $visible, new Name('a'), false, 0);
        $implicit = $resolver->match($environment, $visible, new Name('_rowid_'), true, 0);
        $missing = $resolver->match($environment, $open, new Name('a'), true, 0);
        $random = $resolver->match($environment, $unnamed, new Name('a'), true, 0);

        self::assertInstanceOf(ResolvedColumn::class, $slot);
        self::assertSame($fact->shape->slots[2], $slot->slot);
        self::assertSame(2, $slot->depth);
        self::assertSame([], $hidden);
        self::assertInstanceOf(ResolvedColumn::class, $reached);
        self::assertInstanceOf(ResolvedColumn::class, $implicit);
        self::assertSame($t->declarations()[0]->columns[0], $implicit->declaration());
        self::assertSame([], $resolver->match($environment, $visible, new Name('zz'), true, 0));
        self::assertIsArray($missing);
        self::assertInstanceOf(UndeclaredRelation::class, $missing[0]);
        self::assertIsArray($random);
        self::assertInstanceOf(RandomColumnName::class, $random[0]);
    }

    public function testQualifiedOnlyIsAPositionNoSlotCanHave(): void
    {
        self::assertSame(-1, ColumnResolver::QUALIFIED_ONLY);
    }
}
