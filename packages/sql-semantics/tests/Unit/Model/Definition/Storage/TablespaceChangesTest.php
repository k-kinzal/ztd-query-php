<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;
use SqlSemantics\Model\Definition\Storage\TablespaceChanges;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(TablespaceChanges::class)]
#[Medium]
final class TablespaceChangesTest extends TestCase
{
    public function testCarriesEveryChangeableProperty(): void
    {
        $changes = new TablespaceChanges(1, 2, 3, 'InnoDB', StorageEncryption::Disabled, '', CompletionWait::NoWait);
        self::assertSame([1, 2, 3, 'InnoDB', StorageEncryption::Disabled, '', CompletionWait::NoWait], [$changes->initialSize, $changes->autoextendSize, $changes->maxSize, $changes->engine, $changes->encryption, $changes->engineAttribute, $changes->waiting]);
    }

    public function testRejectsAnEmptyEngineName(): void
    {
        $this->expectException(InvalidStructure::class);
        new TablespaceChanges(engine: '');
    }

    public function testRejectsANegativeMaximumSize(): void
    {
        $this->expectException(InvalidStructure::class);
        new TablespaceChanges(maxSize: -5);
    }
}
