<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryColumnPolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(PrimaryColumnPolicy::class)]
#[Small]
final class PrimaryColumnPolicyTest extends TestCase
{
    public function testEffectiveDefaultDistinguishesTheLegacyTableAndInlineRoles(): void
    {
        $table = new PrimaryColumnPolicy(GrammarRelease::MySql5651, false);
        $inline = new PrimaryColumnPolicy(GrammarRelease::MySql5651, true);

        $default = $table->effectiveDefault(null, null);

        self::assertInstanceOf(IntegerLiteral::class, $default);
        self::assertSame('0', $default->decimal);
        self::assertNull($inline->effectiveDefault(null, null));
    }
}
