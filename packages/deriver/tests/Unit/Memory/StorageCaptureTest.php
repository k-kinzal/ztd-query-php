<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Deriver\Memory\Location;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Memory\StorageCapture
 */
#[CoversClass(StorageCapture::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(Term::class)]
#[Small]
final class StorageCaptureTest extends TestCase
{
    public function testCapturePreservesSharedCellsAndCyclesWithoutIncludingUnreachableLocals(): void
    {
        $memory = new Memory();
        $memory->cells = ['a' => new Term('object', 'one'), 'object:one' => Term::array(['self' => new Term('object', 'one'), 'ref' => new Term('cell', 'a')]), 'unused' => Term::constant(99), 'global:g' => Term::constant(2)];
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
        $memory->cells['model:one'] = Term::array(['slot' => Term::constant(4)]);
        $capture = new StorageCapture();
        self::assertSame(['object:one', 'model:one'], $capture->roots(new Term('object', 'one'), $memory));
        self::assertSame([], $capture->roots(Term::constant(1), $memory));
        self::assertSame('uninitialized', $capture->capture($memory, [], ['return' => new Term('object', 'one')])->cells['object:one']->kind);
    }
    public function testCaptureCarriesConfidentialityThroughReachableObjectCycles(): void
    {
        $memory = new Memory();
        $memory->cells = ['object:a' => Term::array(['child' => new Term('object', 'b')]), 'object:b' => Term::array(['parent' => new Term('object', 'a'), 'payload' => Term::constant('secret')])];
        $storage = (new StorageCapture())->capture($memory, [], ['return' => new Term('object', 'a', secret: true)]);
        self::assertTrue($storage->cells['object:a']->secret);
        self::assertTrue($storage->cells['object:b']->secret);
    }
}
