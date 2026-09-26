<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Api\Result\StorageSnapshot
 */
#[CoversClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class StorageSnapshotTest extends TestCase
{
    public function testStoragePreservesIdentityGraphs(): void
    {
        $value = new \Deriver\Value\Term('cell', 'shared');
        $storage = new \Deriver\Api\Result\StorageSnapshot(['a' => $value, 'b' => $value], ['shared' => \Deriver\Value\Term::constant(3)]);
        self::assertSame($storage->bindings['a'], $storage->bindings['b']);
        self::assertSame(3, $storage->cells['shared']->native());
    }
}
