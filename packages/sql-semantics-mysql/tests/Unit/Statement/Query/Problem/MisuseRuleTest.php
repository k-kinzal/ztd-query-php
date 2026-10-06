<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;

#[CoversClass(MisuseRule::class)]
#[Small]
final class MisuseRuleTest extends TestCase
{
    public function testCasesHoldTheServerMessages(): void
    {
        self::assertSame('No tables used', MisuseRule::StarWithoutTables->value);
        self::assertSame('Every derived table must have its own alias', MisuseRule::DerivedWithoutAlias->value);
    }
}
