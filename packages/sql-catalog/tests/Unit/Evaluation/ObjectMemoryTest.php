<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectMemory;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

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
}
