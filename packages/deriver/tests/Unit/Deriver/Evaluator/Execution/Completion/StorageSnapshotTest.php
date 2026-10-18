<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Completion;

use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot
 */
#[CoversClass(StorageSnapshot::class)]
#[UsesClass(Term::class)]
#[Small]
final class StorageSnapshotTest extends TestCase
{
    public function testStoragePreservesIdentityGraphs(): void
    {
        $value = new Term(\Deriver\Deriver\Evaluator\TermKind::Cell, 'shared');
        $storage = new StorageSnapshot(['a' => $value, 'b' => $value], ['shared' => Term::constant(3)]);
        self::assertSame($storage->bindings['a'], $storage->bindings['b']);
        self::assertSame(3, $storage->cells['shared']->native());
    }
}
