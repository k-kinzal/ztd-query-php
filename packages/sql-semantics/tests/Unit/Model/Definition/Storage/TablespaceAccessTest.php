<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Storage\TablespaceAccess;

#[CoversClass(TablespaceAccess::class)]
#[Medium]
final class TablespaceAccessTest extends TestCase
{
    public function testCasesSpellEveryLegacyAccessMode(): void
    {
        self::assertSame(['READ_ONLY', 'READ_WRITE', 'NOT ACCESSIBLE'], array_map(static fn (TablespaceAccess $mode): string => $mode->value, TablespaceAccess::cases()));
    }
}
