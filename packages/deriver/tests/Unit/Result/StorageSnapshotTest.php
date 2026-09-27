<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use Deriver\Result\StorageSnapshot;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Result\StorageSnapshot
 */
#[CoversClass(StorageSnapshot::class)]
#[UsesClass(Term::class)]
#[Small]
final class StorageSnapshotTest extends TestCase
{
    public function testStoragePreservesIdentityGraphs(): void
    {
        $value = new Term('cell', 'shared');
        $storage = new StorageSnapshot(['a' => $value, 'b' => $value], ['shared' => Term::constant(3)]);
        self::assertSame($storage->bindings['a'], $storage->bindings['b']);
        self::assertSame(3, $storage->cells['shared']->native());
    }
}
