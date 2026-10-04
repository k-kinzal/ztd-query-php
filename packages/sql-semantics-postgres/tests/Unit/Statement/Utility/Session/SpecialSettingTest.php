<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::class)]
#[Small]
final class SpecialSettingTest extends TestCase
{
    public function testParameterOfEachSetting(): void
    {
        self::assertSame(['search_path', 'client_encoding', 'role', 'session_authorization', 'xmloption', null, null], [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::Schema->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::Names->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::Role->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::SessionAuthorization->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::XmlOption->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::Catalog->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::TransactionSnapshot->parameter()]);
    }
}
