<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;

#[CoversClass(RoleOrPrivilegeMismatch::class)]
#[Small]
final class RoleOrPrivilegeMismatchTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('r@h is not a privilege: illegal privilege identifier.', (new RoleOrPrivilegeMismatch('r@h', false))->message());
    }
}
