<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

#[CoversClass(Snapshot::class)]
#[Small]
final class SnapshotTest extends TestCase
{
    public function testCloningIsRefused(): void
    {
        $name = new Name('id');

        $this->expectExceptionMessage('Semantic values cannot be cloned.');

        self::assertNotSame($name, clone $name);
    }

    public function testSerializationIsRefused(): void
    {
        $name = new Name('id');

        $this->expectExceptionMessage('Semantic values must be constructed, not serialized.');

        serialize($name);
    }

    public function testRestorationWithoutConstructionIsRefused(): void
    {
        $this->expectExceptionMessage('Semantic values cannot be restored without construction.');

        unserialize(sprintf('O:%d:"%s":1:{s:5:"value";s:2:"id";}', strlen(Name::class), Name::class));
    }
}
