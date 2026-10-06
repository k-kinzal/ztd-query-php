<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction;

#[CoversClass(FactorAction::class)]
#[Small]
final class FactorActionTest extends TestCase
{
    public function testCasesSpellTheActions(): void
    {
        self::assertSame(['ADD', 'MODIFY', 'DROP'], array_column(FactorAction::cases(), 'value'));
    }
}
