<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipSetting::class)]
#[Small]
final class MembershipSettingTest extends TestCase
{
    public function testEnabledUnlessFalse(): void
    {
        self::assertTrue(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipSetting::Option->enabled());
        self::assertTrue(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipSetting::True->enabled());
        self::assertFalse(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipSetting::False->enabled());
    }
}
