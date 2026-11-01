<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Session\Parse\Entry\RoleInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;

#[CoversClass(RoleInput::class)]
#[Small]
final class RoleInputTest extends TestCase
{
    public function testErrorUsesTheLocatedOperandAndLine(): void
    {
        $error = (new RoleInput())->error(new RoleOrPrivilegeMismatch('SELECT', true, 9), "GRANT r,\nSELECT TO u");

        self::assertSame("Illegal authorization identifier near 'SELECT TO u' at line 2", $error->getMessage());
    }
}
