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
use SqlSemantics\Platform\Sqlite\Rules\Resolution\FromScope;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOn;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\Sqlite\Statement\Relation\NestedInput;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableCall;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;

#[CoversClass(FromScope::class)]
#[Medium]
final class FromScopeTest extends TestCase
{
    public function testOpenMakesATableVisibleWithItsNameAliasAndRowIdentifier(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $input = new TableInput(new QualifiedName(new Name('t')), new Name('x'));
        $joined = (new FromScope())->open($input, $derivation, $derivation->environment());

        self::assertInstanceOf(DeclaredTable::class, $joined->fact->table);
        self::assertCount(1, $joined->visible);
        self::assertSame($input, $joined->visible[0]->relation);
        self::assertSame($joined->fact->shape, $joined->visible[0]->shape);
        self::assertSame('x', $joined->visible[0]->alias?->value);
        self::assertSame($input->name, $joined->visible[0]->name);
        self::assertSame([], $joined->visible[0]->hidden);
        self::assertCount(1, $joined->visible[0]->implicit);
        self::assertSame($joined->fact, $derivation->facts()->relation($input));
    }

    public function testOpenEntersAChainOrANestedInputAndRecordsItsFact(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $chain = new JoinChain(new TableInput(new QualifiedName(new Name('t'))), [new JoinStep(new JoinOperator(), new TableInput(new QualifiedName(new Name('u'))), new JoinUsing([new Name('a')]))]);
        $nested = new NestedInput($chain);
        $joined = (new FromScope())->open($nested, $derivation, $derivation->environment());
        $facts = $derivation->facts();

        self::assertCount(2, $joined->visible);
        self::assertSame([0], $joined->visible[1]->hidden);
        self::assertCount(4, $joined->fact->shape->slots);
        self::assertSame($joined->fact, $facts->relation($nested));
        self::assertSame($joined->fact, $facts->relation($chain));
        self::assertTrue($facts->covers($chain->first));
        self::assertSame([], $facts->diagnostics);
    }

    public function testOpenResolvesTableCallArgumentsAgainstTheTermsToTheLeft(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $scope = new FromScope();
        $left = $scope->open(new TableInput(new QualifiedName(new Name('t'))), $derivation, $derivation->environment())->visible;
        $argument = new ColumnUse(new Name('b'));
        $call = new TableCall(new QualifiedName(new Name('json_each')), [$argument], new Name('j'));
        $joined = $scope->open($call, $derivation, $derivation->environment(), $left, false);
        $resolution = $derivation->facts()->scalar($argument)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($left[0]->relation, $resolution->relation);
        self::assertCount(1, $joined->visible);
        self::assertSame('j', $joined->visible[0]->alias?->value);
        self::assertSame($call->name, $joined->visible[0]->name);
        self::assertFalse($joined->fact->shape->complete());
    }

    public function testEnterReportsConstraintsWithoutAJoinAndInvalidOperators(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $on = new JoinOn(new IntegerLiteral('1'));
        $step = new JoinStep(new JoinOperator(false, [JoinKeyword::Natural, JoinKeyword::Outer]), new TableInput(new QualifiedName(new Name('t')), new Name('t2')), $on);
        $chain = new JoinChain(new TableInput(new QualifiedName(new Name('t'))), [$step], new JoinUsing([new Name('a')]));
        $joined = (new FromScope())->enter($chain, $derivation, $derivation->environment(), [], true);
        $rules = array_map(static fn (object $diagnostic): ?MisuseRule => $diagnostic instanceof Misuse ? $diagnostic->rule : null, $derivation->facts()->diagnostics);

        self::assertSame([MisuseRule::UnknownJoinType, MisuseRule::UsingWithoutJoin], $rules);
        self::assertCount(2, $joined->visible);
        self::assertCount(6, $joined->fact->shape->slots);
        self::assertTrue($derivation->facts()->covers($on->condition));
    }

    public function testEnterDerivesEveryOnConditionInTheScopeOfTheWholeChain(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $condition = new ColumnUse(new Name('c'), new QualifiedName(new Name('u')));
        $first = new TableInput(new QualifiedName(new Name('t')));
        $second = new TableInput(new QualifiedName(new Name('u')));
        $chain = new JoinChain($first, [new JoinStep(new JoinOperator(false, [JoinKeyword::Left]), $second, new JoinOn($condition))]);
        $joined = (new FromScope())->enter($chain, $derivation, $derivation->environment(), [], true);
        $resolution = $derivation->facts()->scalar($condition)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($second, $resolution->relation);
        self::assertSame($joined->visible[1]->shape->slots[1], $resolution->slot);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testNestedRenamesASingleTermByTheAliasOrKeepsItsRelations(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $scope = new FromScope();
        $aliased = $scope->nested(new NestedInput(new TableInput(new QualifiedName(new Name('t'))), new Name('x')), $derivation, $derivation->environment(), true);
        $plain = $scope->nested(new NestedInput(new TableInput(new QualifiedName(new Name('t')))), $derivation, $derivation->environment(), true);

        self::assertCount(1, $aliased->visible);
        self::assertSame('x', $aliased->visible[0]->alias?->value);
        self::assertSame('t', $aliased->visible[0]->name?->name->value);
        self::assertCount(1, $aliased->visible[0]->implicit);
        self::assertCount(1, $plain->visible);
        self::assertNull($plain->visible[0]->alias);
    }

    public function testNestedAddsAQualifiedOnlyRelationForAnAliasedJoin(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $scope = new FromScope();
        $chain = new JoinChain(new TableInput(new QualifiedName(new Name('t'))), [new JoinStep(new JoinOperator(), new TableInput(new QualifiedName(new Name('u'))), new JoinUsing([new Name('a')]))]);
        $node = new NestedInput($chain, new Name('j'));
        $aliased = $scope->nested($node, $derivation, $derivation->environment(), true);
        $plain = $scope->nested(new NestedInput($chain), $derivation, $derivation->environment(), false);

        self::assertCount(3, $aliased->visible);
        self::assertSame($node, $aliased->visible[2]->relation);
        self::assertSame('j', $aliased->visible[2]->alias?->value);
        self::assertSame([ColumnResolver::QUALIFIED_ONLY], $aliased->visible[2]->hidden);
        self::assertSame($aliased->fact->shape, $aliased->visible[2]->shape);
        self::assertCount(2, $plain->visible);
        self::assertSame($chain->first, $plain->visible[0]->relation);
    }

    public function testStarListsTheUnhiddenSlotsOfQualifiableRelationsOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t], false));
        $shapes = new TableShapes();
        $declared = new TableInput(new QualifiedName(new Name('t'), new Name('main')));
        $undeclared = new TableInput(new QualifiedName(new Name('u')));
        $left = $shapes->fact($derivation, $declared->name, $derivation->environment())->shape;
        $right = $shapes->fact($derivation, $undeclared->name, $derivation->environment())->shape;
        $shape = (new FromScope())->star([
            new VisibleRelation($declared, $left, null, $declared->name, [1]),
            new VisibleRelation($undeclared, $right, null, $undeclared->name),
            new VisibleRelation($declared, $left, new Name('j'), null, [ColumnResolver::QUALIFIED_ONLY]),
        ]);

        self::assertSame(['id', 'b'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
        self::assertSame($right->missing, $shape->missing);
        self::assertFalse($shape->complete());
    }
}
