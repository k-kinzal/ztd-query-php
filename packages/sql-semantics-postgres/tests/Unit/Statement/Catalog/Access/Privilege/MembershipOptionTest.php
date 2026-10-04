<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipOption::class)]
#[Medium]
final class MembershipOptionTest extends TestCase
{
    public function testRecognizedNamesOfTheServer(): void
    {
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipOption(new \SqlSemantics\Statement\Identifier\Name('set'), \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipSetting::True))->recognized());
        self::assertFalse((new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipOption(new \SqlSemantics\Statement\Identifier\Name('ADMIN'), \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipSetting::True))->recognized());
    }

    public function testRenderWritesTheNameAndTheValue(): void
    {
        self::assertSame('GRANT a TO b WITH admin OPTION, inherit FALSE, set TRUE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT a TO b WITH ADMIN OPTION, inherit FALSE, "set" true')->toString());
    }
}
