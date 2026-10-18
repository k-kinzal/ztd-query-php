<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Memory;

use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture
 */
#[CoversClass(StorageCapture::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot::class)]
#[UsesClass(Term::class)]
#[Small]
final class StorageCaptureTest extends TestCase
{
    public function testCapturePreservesSharedCellsAndCyclesWithoutIncludingUnreachableLocals(): void
    {
        $memory = new Memory();
        $memory->cells = ['a' => new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'one'), 'object:one' => Term::array(['self' => new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'one'), 'ref' => new Term(\Deriver\Deriver\Evaluator\TermKind::Cell, 'a')]), 'unused' => Term::constant(99), 'global:g' => Term::constant(2)];
        $storage = (new StorageCapture())->capture($memory, ['x' => new Location('a'), 'y' => new Location('a')]);
        self::assertSame('a', $storage->bindings['x']->literal);
        self::assertSame('a', $storage->bindings['y']->literal);
        self::assertSame(['a', 'global:g', 'object:one'], array_keys($storage->cells));
        self::assertSame('one', $storage->cells['object:one']->operands['self']->literal);
        self::assertSame('a', $storage->cells['object:one']->operands['ref']->literal);
    }
    public function testRootsIncludesRegisteredModelStateAndReturnedObjects(): void
    {
        $memory = new Memory();
        $memory->cells['evaluator:one'] = Term::array(['slot' => Term::constant(4)]);
        $capture = new StorageCapture();
        self::assertSame(['object:one', 'evaluator:one'], $capture->roots(new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'one'), $memory));
        self::assertSame([], $capture->roots(Term::constant(1), $memory));
        self::assertSame(\Deriver\Deriver\Evaluator\TermKind::Uninitialized, $capture->capture($memory, [], ['return' => new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'one')])->cells['object:one']->kind);
    }
    public function testCapturePreservesValuesAcrossReachableObjectCycles(): void
    {
        $memory = new Memory();
        $memory->cells = ['object:a' => Term::array(['child' => new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'b')]), 'object:b' => Term::array(['parent' => new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'a'), 'payload' => Term::constant('payload-value')])];
        $storage = (new StorageCapture())->capture($memory, [], ['return' => new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'a')]);
        self::assertSame(['object:a', 'object:b'], array_keys($storage->cells));
        self::assertSame($memory->cells['object:a'], $storage->cells['object:a']);
        self::assertSame($memory->cells['object:b'], $storage->cells['object:b']);
        self::assertSame('payload-value', $storage->cells['object:b']->operands['payload']->native());
    }
}
