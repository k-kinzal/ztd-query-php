<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderCookie;

#[CoversClass(HeaderCookie::class)]
#[Small]
final class HeaderCookieTest extends TestCase
{
    public function testCasesExposeOnlyWritableCookies(): void
    {
        self::assertSame(['application_id', 'user_version', 'schema_version'], array_column(HeaderCookie::cases(), 'value'));
    }
}
