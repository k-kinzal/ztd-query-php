<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;

#[CoversClass(PrivilegeScope::class)]
#[Medium]
final class PrivilegeScopeTest extends TestCase
{
    public function testCasesAreSpelledAsTheirWildcards(): void
    {
        self::assertSame(['*.*', '*'], array_column(PrivilegeScope::cases(), 'value'));
    }
}
