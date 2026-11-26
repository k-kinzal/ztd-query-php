<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagArgument;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagChange;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;

#[CoversClass(FlagArgument::class)]
#[Small]
final class FlagArgumentTest extends TestCase
{
    public function testChangeDistinguishesReadReloadAndText(): void
    {
        $rule = new FlagArgument();

        self::assertNull($rule->change(FlagName::ForeignKeys, null));
        self::assertSame(FlagChange::Reset, $rule->change(FlagName::WritableSchema, new Name('ReSeT')));
        self::assertSame(FlagChange::Disable, $rule->change(FlagName::ForeignKeys, new Name('ReSeT')));
    }

    public function testNonzeroTestsTheLowByteOfTheBoundedInteger(): void
    {
        $rule = new FlagArgument();

        self::assertFalse($rule->nonzero('00000100', '7fffffff', 16));
        self::assertTrue($rule->nonzero('00000101', '7fffffff', 16));
        self::assertFalse($rule->nonzero('00002147483999', '2147483647', 10));
    }
}
