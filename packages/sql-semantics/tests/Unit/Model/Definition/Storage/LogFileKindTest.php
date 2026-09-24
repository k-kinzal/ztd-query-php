<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Storage\LogFileKind;

#[CoversClass(LogFileKind::class)]
#[Medium]
final class LogFileKindTest extends TestCase
{
    public function testCasesSpellBothLogFileKeywords(): void
    {
        self::assertSame(['UNDOFILE', 'REDOFILE'], array_map(static fn (LogFileKind $kind): string => $kind->value, LogFileKind::cases()));
    }
}
