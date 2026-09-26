<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Memory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Memory\StorageCapture
 */
#[CoversClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class StorageCaptureTest extends TestCase
{
    public function testCapturePreservesSharedCellsAndCyclesWithoutIncludingUnreachableLocals(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $memory->cells = ['a' => new \Deriver\Value\Term('object', 'one'), 'object:one' => \Deriver\Value\Term::array(['self' => new \Deriver\Value\Term('object', 'one'), 'ref' => new \Deriver\Value\Term('cell', 'a')]), 'unused' => \Deriver\Value\Term::constant(99), 'global:g' => \Deriver\Value\Term::constant(2)];
        $storage = (new \Deriver\Internal\Memory\StorageCapture())->capture($memory, ['x' => new \Deriver\Internal\Memory\Location('a'), 'y' => new \Deriver\Internal\Memory\Location('a')]);
        self::assertSame('a', $storage->bindings['x']->literal);
        self::assertSame('a', $storage->bindings['y']->literal);
        self::assertSame(['a', 'global:g', 'object:one'], array_keys($storage->cells));
        self::assertSame('one', $storage->cells['object:one']->operands['self']->literal);
        self::assertSame('a', $storage->cells['object:one']->operands['ref']->literal);
    }
    public function testRootsIncludesRegisteredModelStateAndReturnedObjects(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $memory->cells['model:one'] = \Deriver\Value\Term::array(['slot' => \Deriver\Value\Term::constant(4)]);
        $capture = new \Deriver\Internal\Memory\StorageCapture();
        self::assertSame(['object:one', 'model:one'], $capture->roots(new \Deriver\Value\Term('object', 'one'), $memory));
        self::assertSame([], $capture->roots(\Deriver\Value\Term::constant(1), $memory));
        self::assertSame('uninitialized', $capture->capture($memory, [], ['return' => new \Deriver\Value\Term('object', 'one')])->cells['object:one']->kind);
    }
    public function testCaptureCarriesConfidentialityThroughReachableObjectCycles(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $memory->cells = ['object:a' => \Deriver\Value\Term::array(['child' => new \Deriver\Value\Term('object', 'b')]), 'object:b' => \Deriver\Value\Term::array(['parent' => new \Deriver\Value\Term('object', 'a'), 'payload' => \Deriver\Value\Term::constant('secret')])];
        $storage = (new \Deriver\Internal\Memory\StorageCapture())->capture($memory, [], ['return' => new \Deriver\Value\Term('object', 'a', secret: true)]);
        self::assertTrue($storage->cells['object:a']->secret);
        self::assertTrue($storage->cells['object:b']->secret);
    }
}
