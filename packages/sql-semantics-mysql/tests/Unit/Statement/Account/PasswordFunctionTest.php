<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;

#[CoversClass(PasswordFunction::class)]
#[Small]
final class PasswordFunctionTest extends TestCase
{
    public function testCasesSpellTheFunctions(): void
    {
        self::assertSame(['PASSWORD', 'OLD_PASSWORD'], array_column(PasswordFunction::cases(), 'value'));
    }
}
