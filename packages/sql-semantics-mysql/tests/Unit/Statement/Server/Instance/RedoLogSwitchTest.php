<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\RedoLogSwitch;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RedoLogSwitch::class)]
#[Medium]
final class RedoLogSwitchTest extends TestCase
{
    public function testRenderWritesTheNamesAsWritten(): void
    {
        self::assertSame('ALTER INSTANCE ENABLE InnoDB REDO_LOG', (new Inspection())->inspect('alter instance enable InnoDB REDO_LOG')->toString());
    }
}
