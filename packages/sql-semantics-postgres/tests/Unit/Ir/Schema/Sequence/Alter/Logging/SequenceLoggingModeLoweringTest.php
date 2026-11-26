<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\Logging\SequenceLoggingModeLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind;

#[CoversClass(SequenceLoggingModeLowering::class)]
final class SequenceLoggingModeLoweringTest extends TestCase
{
    public function testLowerResolvesTheNativeLoggingAction(): void
    {
        self::assertSame(SequenceLogging::Unlogged, (new SequenceLoggingModeLowering())->lower(new TableAction(TableActionKind::SetUnlogged)));
    }
}
