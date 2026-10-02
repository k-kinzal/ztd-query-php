<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\MySql\Access;

#[CoversClass(Access::class)]
#[Small]
final class AccessTest extends TestCase
{
    #[TestWith([Access::SessionDefault, ''])]
    #[TestWith([Access::ReadOnly, 'READ ONLY'])]
    #[TestWith([Access::ReadWrite, 'READ WRITE'])]
    #[TestWith([Access::Conflicting, 'READ ONLY, READ WRITE'])]
    public function testToStringKeepsDefaultsAndContradictionsDistinct(Access $access, string $expected): void
    {
        self::assertSame($expected, $access->toString());
    }
}
