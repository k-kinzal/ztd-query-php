<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFormat;

#[CoversClass(CopyFormat::class)]
final class CopyFormatTest extends TestCase
{
    public function testCasesUseTheServerArguments(): void
    {
        self::assertSame(['text', 'csv', 'binary'], array_map(static fn (CopyFormat $case): string => $case->value, CopyFormat::cases()));
    }
}
