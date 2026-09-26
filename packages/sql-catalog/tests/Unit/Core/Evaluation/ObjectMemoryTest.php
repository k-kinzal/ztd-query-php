<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\ObjectMemory;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Evaluation\PatternTerm;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextGeneralization;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(ObjectMemory::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TypeShape::class)]
final class ObjectMemoryTest extends TestCase
{
    public function testRememberMakesAliasesReadTheLatestSnapshot(): void
    {
        $memory = new ObjectMemory();
        $before = new ObjectTerm('Builder', identity: 'a');
        $after = new ObjectTerm('Builder', identity: 'a', state: new ArrayTerm([]));
        $memory->remember($after);
        self::assertSame($after, $memory->read(Domain::of($before))->soleObject());
        $memory->remember(new ObjectTerm('Untracked'));
        self::assertSame($after, $memory->read(Domain::of($before))->soleObject());
    }

    public function testRememberValueRetainsAlternativeSnapshotsAndBudgetFlags(): void
    {
        $a = new ObjectTerm('Builder', identity: 'a');
        $b = new ObjectTerm('Builder', identity: 'a', state: new ArrayTerm([]));
        $memory = new ObjectMemory();
        $memory->rememberValue(Domain::fromTerms([$a, $b], true, true));
        $value = $memory->read(Domain::of($a));
        self::assertCount(2, $value->terms);
        self::assertTrue($value->widened);
        self::assertTrue($value->combined);
    }

    public function testImportDoesNotOverwriteAMutatedObjectWithAnOldAlias(): void
    {
        $before = new ObjectTerm('Builder', identity: 'a');
        $after = new ObjectTerm('Builder', identity: 'a', state: new ArrayTerm([]));
        $memory = new ObjectMemory();
        $memory->remember($after);
        $memory->import(Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::of($before))])));
        self::assertSame($after, $memory->read(Domain::of($before))->soleObject());
    }

    public function testReadRefreshesObjectsStoredInArrays(): void
    {
        $object = new ObjectTerm('Builder', identity: 'a');
        $after = new ObjectTerm('Builder', identity: 'a', state: new ArrayTerm([]));
        $array = Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::of($object))]));
        $memory = new ObjectMemory();
        self::assertSame($array, $memory->read($array));
        $memory->remember($after);
        self::assertSame($after, $memory->read($array)->soleArray()?->positional()[0]->soleObject());
    }

    public function testCopyIsIndependentOfLaterMutations(): void
    {
        $object = new ObjectTerm('Builder', identity: 'a');
        $memory = new ObjectMemory();
        $memory->remember($object);
        $copy = $memory->copy();
        $copy->remember(new ObjectTerm('Builder', identity: 'a', state: new ArrayTerm([])));
        self::assertSame($object, $memory->read(Domain::of($object))->soleObject());
        self::assertNotSame($memory->signature(), $copy->signature());
    }

    public function testJoinPreservesBothBranchesWithoutMutatingEither(): void
    {
        $object = new ObjectTerm('Builder', identity: 'a');
        $left = new ObjectMemory();
        $left->remember($object);
        $right = $left->copy();
        $right->remember(new ObjectTerm('Builder', identity: 'a', state: new ArrayTerm([])));
        self::assertCount(2, $left->join($right)->read(Domain::of($object))->terms);
        self::assertSame($object, $left->read(Domain::of($object))->soleObject());
    }

    public function testSignatureDoesNotDependOnInsertionOrder(): void
    {
        $a = new ObjectTerm('Builder', identity: 'a');
        $b = new ObjectTerm('Builder', identity: 'b');
        $left = new ObjectMemory();
        $right = new ObjectMemory();
        $left->remember($a);
        $left->remember($b);
        $right->remember($b);
        $right->remember($a);
        self::assertSame($left->signature(), $right->signature());
    }
    public function testInvalidateOpensAliasedObjectsInsideArraysAndKeepsUntrackedValues(): void
    {
        $memory = new ObjectMemory();
        $object = new ObjectTerm('Demo', identity: 'demo:1', state: new ArrayTerm([]));
        $value = Domain::fromTerms([new ArrayTerm([new ArrayEntry(null, Domain::of($object))]), new ObjectTerm('Untracked')], true, true);
        $opened = $memory->invalidate($value);
        self::assertNull($memory->read(Domain::of($object))->soleObject()?->state);
        self::assertTrue($opened->widened);
        self::assertTrue($opened->combined);
        self::assertSame('Untracked', $opened->terms[1]->type()->soleClassName());
    }

}
