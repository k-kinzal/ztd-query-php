<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(FactorChange::class)]
#[Medium]
final class FactorChangeTest extends TestCase
{
    public function testRenderWritesEveryStep(): void
    {
        self::assertSame('ALTER USER u DROP 2 FACTOR DROP 3 FACTOR', (new Inspection())->inspect('alter user u drop 2 factor drop 3 factor')->toString());
    }
}
