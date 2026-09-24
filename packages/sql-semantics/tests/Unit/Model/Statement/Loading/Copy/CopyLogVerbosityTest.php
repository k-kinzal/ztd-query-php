<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\CopyLogVerbosity;

#[CoversClass(CopyLogVerbosity::class)]
final class CopyLogVerbosityTest extends TestCase
{
    public function testCasesUseTheServerArguments(): void
    {
        self::assertSame(['default', 'verbose'], array_map(static fn (CopyLogVerbosity $case): string => $case->value, CopyLogVerbosity::cases()));
    }
}
