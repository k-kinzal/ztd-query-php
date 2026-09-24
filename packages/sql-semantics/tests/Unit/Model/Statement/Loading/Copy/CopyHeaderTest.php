<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\CopyHeader;

#[CoversClass(CopyHeader::class)]
final class CopyHeaderTest extends TestCase
{
    public function testCasesUseTheServerArguments(): void
    {
        self::assertSame(['false', 'true', 'match'], array_map(static fn (CopyHeader $case): string => $case->value, CopyHeader::cases()));
    }
}
