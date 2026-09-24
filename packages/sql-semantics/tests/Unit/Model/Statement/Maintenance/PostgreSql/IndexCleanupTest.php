<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\IndexCleanup;

#[CoversClass(IndexCleanup::class)]
final class IndexCleanupTest extends TestCase
{
    public function testCasesUseTheSqlArguments(): void
    {
        self::assertSame(['AUTO', 'ON', 'OFF'], array_map(static fn (IndexCleanup $cleanup): string => $cleanup->value, IndexCleanup::cases()));
    }
}
