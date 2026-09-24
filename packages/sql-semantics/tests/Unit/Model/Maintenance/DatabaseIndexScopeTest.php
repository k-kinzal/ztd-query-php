<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Maintenance\DatabaseIndexScope;

#[CoversClass(DatabaseIndexScope::class)]
final class DatabaseIndexScopeTest extends TestCase
{
    public function testRepresentsUserAndSystemTableSelections(): void
    {
        self::assertSame(['DATABASE', 'SYSTEM'], array_column(DatabaseIndexScope::cases(), 'value'));
        self::assertSame(DatabaseIndexScope::SystemTables, DatabaseIndexScope::from('SYSTEM'));
    }
}
