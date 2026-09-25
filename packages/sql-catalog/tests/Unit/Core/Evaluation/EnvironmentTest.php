<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(Environment::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectMemory::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ObjectTerm::class)]
final class EnvironmentTest extends TestCase
{
    public function testReadFallsBackToAnUnresolvedValue(): void
    {
        self::assertSame('mixed', (new Environment())->read('missing')->type()->display());
    }

    public function testWriteBindsAVariable(): void
    {
        $environment = new Environment();
        $environment->write('sql', Domain::literal('SELECT 1'));
        self::assertSame('SELECT 1', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testHasReportsWhetherAVariableIsBound(): void
    {
        $environment = new Environment(['sql' => Domain::literal('x')]);
        self::assertTrue($environment->has('sql'));
        self::assertFalse($environment->has('other'));
    }

    public function testForgetDropsABinding(): void
    {
        $environment = new Environment(['sql' => Domain::literal('x')]);
        $environment->forget('sql');
        self::assertFalse($environment->has('sql'));
    }

    public function testNamesListsTheBoundVariables(): void
    {
        self::assertSame(['a', 'b'], (new Environment([
            'a' => Domain::literal(1),
            'b' => Domain::literal(2),
        ]))->names());
    }

    public function testCopyIsIndependentOfTheOriginal(): void
    {
        $environment = new Environment(['a' => Domain::literal(1)]);
        $copy = $environment->copy();
        $copy->write('a', Domain::literal(2));
        self::assertSame(1, $environment->read('a')->soleLiteral()?->value);
    }

    public function testJoinKeepsWhatEitherSideMayHold(): void
    {
        $left = new Environment(['a' => Domain::literal(1)]);
        $right = new Environment(['a' => Domain::literal(2), 'b' => Domain::literal(3)]);
        $joined = $left->join($right);
        self::assertCount(2, $joined->read('a')->terms);
        self::assertCount(2, $joined->read('b')->terms);
    }

    public function testJoinKeepsABindingOnlyOneSideHas(): void
    {
        $joined = (new Environment(['a' => Domain::literal(1)]))->join(new Environment());
        self::assertCount(2, $joined->read('a')->terms);
    }

    public function testSignatureWritesEachBindingSortedByName(): void
    {
        $environment = new Environment([
            'b' => Domain::literal(2),
            'a' => Domain::literal('x')->union(Domain::literal('w')),
        ]);

        self::assertSame(
            'a=literal:string:w|literal:string:x;b=literal:int:2',
            $environment->signature(),
        );
    }

    public function testSignatureIsEmptyWithoutBindings(): void
    {
        self::assertSame('', (new Environment())->signature());
    }

    public function testSignatureIsTheSameForTheSameBindingsInAnyOrder(): void
    {
        $left = new Environment(['a' => Domain::literal(1), 'b' => Domain::literal('x')]);
        $right = new Environment(['b' => Domain::literal('x'), 'a' => Domain::literal(1)]);

        self::assertSame($left->signature(), $right->signature());
    }

    public function testSignatureDiffersForDifferentBindings(): void
    {
        $environment = new Environment(['a' => Domain::literal(1)]);

        self::assertNotSame($environment->signature(), (new Environment(['a' => Domain::literal('1')]))->signature());
        self::assertNotSame($environment->signature(), (new Environment(['b' => Domain::literal(1)]))->signature());
        self::assertNotSame($environment->signature(), (new Environment(['a' => Domain::literal(1), 'b' => Domain::literal(1)]))->signature());
    }

    public function testEqualsComparesBindings(): void
    {
        $left = new Environment(['a' => Domain::literal(1)]);
        self::assertTrue($left->equals(new Environment(['a' => Domain::literal(1)])));
        self::assertFalse($left->equals(new Environment(['a' => Domain::literal(2)])));
        self::assertFalse($left->equals(new Environment()));
    }
    public function testPresenceSeparatesUndefinedNullAndUnknown(): void
    {
        $environment = new Environment(['null' => Domain::literal(null)]);
        $environment->markAbsent('missing');
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Present, $environment->presence('null'));
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Absent, $environment->presence('missing'));
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Maybe, $environment->presence('unknown'));
        self::assertSame('', $environment->read('missing')->concat(Domain::literal(''))->soleLiteral()?->value);
        self::assertNotSame($environment->signature(), (new Environment(['null' => Domain::literal(null), 'missing' => Domain::literal(null)]))->signature());
    }

    public function testMarkAbsentSurvivesCopyAndJoin(): void
    {
        $absent = new Environment();
        $absent->markAbsent('part');
        $joined = $absent->copy()->join(new Environment(['part' => Domain::literal('tail')]));
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Maybe, $joined->presence('part'));
        self::assertSame('literal:null:|literal:string:tail', $joined->read('part')->signature());
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Absent, $absent->presence('part'));
    }

    public function testInvalidateDoesNotTurnUnknownWritesIntoEmptyStrings(): void
    {
        $environment = new Environment();
        $environment->markAbsent('part');
        $environment->invalidate('part');
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Maybe, $environment->presence('part'));
        self::assertNull($environment->read('part')->concat(Domain::literal(''))->soleLiteral());
        $environment->write('part', Domain::literal('known'));
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Present, $environment->presence('part'));
    }

    public function testReplaceRetainsPresenceAndCombinationMetadata(): void
    {
        $environment = new Environment(['old' => Domain::literal('old')]);
        $other = new Environment();
        $other->markAbsent('part');
        $other->combined = true;
        $environment->replace($other);
        self::assertTrue($environment->equals($other));
        self::assertTrue($environment->combined);
        self::assertFalse($environment->has('old'));
    }

    public function testNarrowDoesNotTurnPossibleAbsenceIntoPresentNull(): void
    {
        $environment = new Environment();
        $environment->markAbsent('tail');
        $joined = $environment->join(new Environment(['tail' => Domain::literal('suffix')]));
        $joined->narrow('tail', Domain::literal(null));
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Maybe, $joined->presence('tail'));
        $joined->narrow('tail', Domain::literal('suffix'));
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Present, $joined->presence('tail'));
        $environment->narrow('tail', Domain::literal(null));
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Absent, $environment->presence('tail'));
    }


    public function testJoinPreservesCombinationUncertaintyAndPresenceWithoutInventingIt(): void
    {
        $left = new Environment(['a' => Domain::literal('a')]);
        self::assertFalse($left->join($left)->combined);
        $right = $left->copy();
        $right->combined = true;
        self::assertTrue($left->join($right)->combined);
        self::assertTrue($right->join($left)->combined);
        self::assertTrue($left->join(new Environment(['a' => Domain::literal('b')]))->combined);
        self::assertNotSame($left->signature(), $right->signature());
        $left->invalidate('a');
        $right->invalidate('b');
        self::assertSame('$a', $left->read('a')->terms[0]->toPattern()->holes()[0]->expression);
        self::assertSame('$b', $right->read('b')->terms[0]->toPattern()->holes()[0]->expression);
        $absent = new Environment();
        $absent->markAbsent('a');
        self::assertNotSame($absent->signature(), $left->signature());
        self::assertSame(\SqlCatalog\Core\Evaluation\Presence::Absent, $absent->join($absent)->presence('a'));
    }


    public function testObjectsSharesSnapshotsAcrossAliasesButNotCopies(): void
    {
        $object = new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'a');
        $env = new Environment(['q' => Domain::of($object), 'alias' => Domain::of($object)]);
        $copy = $env->copy();
        $changed = new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'a', state: new \SqlCatalog\Core\Evaluation\ArrayTerm([]));
        $env->objects()->remember($changed);
        self::assertSame($changed, $env->read('alias')->soleObject());
        self::assertSame($object, $copy->read('alias')->soleObject());
        self::assertFalse($env->equals($copy));
    }

    public function testReplaceKeepsJoinedAlternativeObjectStates(): void
    {
        $object = new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'a');
        $env = new Environment(['q' => Domain::of($object)]);
        $left = $env->copy();
        $right = $env->copy();
        $right->objects()->remember(new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'a', state: new \SqlCatalog\Core\Evaluation\ArrayTerm([])));
        $env->replace($left->join($right));
        self::assertCount(2, $env->read('q')->terms);
        self::assertSame($object, $left->read('q')->soleObject());
    }

    public function testRefreshObservesLaterMutationsThroughAnAlreadyEvaluatedReference(): void
    {
        $object = new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'a');
        $value = Domain::of($object);
        $environment = new Environment(['q' => $value]);
        $updated = new \SqlCatalog\Core\Evaluation\ObjectTerm('Builder', identity: 'a', state: new \SqlCatalog\Core\Evaluation\ArrayTerm([]));
        $environment->objects()->remember($updated);
        self::assertSame($updated, $environment->refresh($value)->soleObject());
        self::assertSame($value, (new Environment())->refresh($value));
    }
}
