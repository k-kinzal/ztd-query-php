<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\RedoLogSwitch;

#[CoversClass(RedoLogSwitch::class)]
#[Medium]
final class RedoLogSwitchTest extends TestCase
{
    public function testRenderWritesTheNamesAsWritten(): void
    {
        self::assertSame('ALTER INSTANCE ENABLE InnoDB REDO_LOG', (new Semantics())->inspect('alter instance enable InnoDB REDO_LOG')->toString());
    }
}
