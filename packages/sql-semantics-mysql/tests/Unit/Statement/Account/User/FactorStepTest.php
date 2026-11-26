<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(FactorStep::class)]
#[Medium]
final class FactorStepTest extends TestCase
{
    public function testRenderWritesTheFactorAndItsMethod(): void
    {
        self::assertSame('ALTER USER u ADD 2 FACTOR IDENTIFIED WITH authentication_fido', (new Inspection())->inspect('alter user u add 2 factor identified with authentication_fido')->toString());
    }
}
