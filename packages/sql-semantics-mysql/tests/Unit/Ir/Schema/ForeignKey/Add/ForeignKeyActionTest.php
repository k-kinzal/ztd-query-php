<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction;

#[CoversClass(ForeignKeyAction::class)]
#[Small]
final class ForeignKeyActionTest extends TestCase
{
    public function testSetDefaultRetainsItsNativeMetadata(): void
    {
        self::assertSame('SET DEFAULT', ForeignKeyAction::SetDefault->value);
    }
}
