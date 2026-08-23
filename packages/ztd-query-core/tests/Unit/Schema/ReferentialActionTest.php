<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ReferentialAction;

#[CoversClass(ReferentialAction::class)]
final class ReferentialActionTest extends TestCase
{
    public function testActionsExposeSqlSpelling(): void
    {
        self::assertSame('NO ACTION', ReferentialAction::NoAction->value);
        self::assertSame('RESTRICT', ReferentialAction::Restrict->value);
        self::assertSame('CASCADE', ReferentialAction::Cascade->value);
        self::assertSame('SET NULL', ReferentialAction::SetNull->value);
        self::assertSame('SET DEFAULT', ReferentialAction::SetDefault->value);
    }
}
