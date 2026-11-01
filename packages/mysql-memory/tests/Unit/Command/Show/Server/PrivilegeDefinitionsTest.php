<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Server\PrivilegeDefinitions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(PrivilegeDefinitions::class)]
#[Small]
final class PrivilegeDefinitionsTest extends TestCase
{
    public function testOfIncludesRolesOnlyOnReleasesWithRoleSupport(): void
    {
        $old = array_column(PrivilegeDefinitions::of(GrammarRelease::MySql5744), 0);
        $current = array_column(PrivilegeDefinitions::of(GrammarRelease::MySql847), 0);
        self::assertNotContains('Create role', $old);
        self::assertContains('Create role', $current);
        self::assertContains('Select', $old);
    }
}
