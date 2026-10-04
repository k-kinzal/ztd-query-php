<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Noise\AccessNoise::class)]
#[Small]
final class AccessNoiseTest extends TestCase
{
    public function testPositionsListsTheNoiseWordsOfTheFamily(): void
    {
        self::assertSame(['AlterOptRoleElem: ENCRYPTED PASSWORD Sconst', 'privileges: ALL PRIVILEGES', 'privileges: ALL PRIVILEGES ( columnList )', 'grantee: GROUP_P RoleSpec', 'privilege_target: TABLE qualified_name_list'], array_keys(\SqlSemantics\Platform\PostgreSql\Rules\Noise\AccessNoise::positions()));
        self::assertSame([1], \SqlSemantics\Platform\PostgreSql\Rules\Noise\AccessNoise::positions()['privileges: ALL PRIVILEGES']);
    }
}
