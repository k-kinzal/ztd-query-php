<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash;

#[CoversClass(RejectedPasswordHash::class)]
#[Small]
final class RejectedPasswordHashTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('ALTER USER does not accept IDENTIFIED BY PASSWORD.', (new RejectedPasswordHash())->message());
    }
}
