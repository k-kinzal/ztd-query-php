<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Privilege\PrivilegeLevel;

#[CoversClass(PrivilegeLevel::class)]
#[Medium]
final class PrivilegeLevelTest extends TestCase
{
    public function testCasesNameTheFourMySqlLevels(): void
    {
        self::assertSame(['GLOBAL', 'DATABASE', 'TABLE', 'ROUTINE'], array_column(PrivilegeLevel::cases(), 'value'));
    }
}
