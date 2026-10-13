<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;

#[CoversClass(LoadRule::class)]
#[Small]
final class LoadRuleTest extends TestCase
{
    public function testCasesDistinguishEachOptionConflict(): void
    {
        self::assertCount(6, LoadRule::cases());
        self::assertSame('Incorrect usage of LOAD DATA with BULK Algorithm and LOCAL client file', LoadRule::LocalBulk->value);
    }
}
