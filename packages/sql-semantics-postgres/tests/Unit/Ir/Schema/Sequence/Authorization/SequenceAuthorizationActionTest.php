<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequenceAuthorizationAction;

#[CoversClass(SequenceAuthorizationAction::class)]
final class SequenceAuthorizationActionTest extends TestCase
{
    public function testToStringSelectsTheResolvedLoggingAlternative(): void
    {
        $action = new SequenceAuthorizationAction(SequenceLogging::Logged);

        self::assertSame('SET LOGGED', $action->toString());
    }
}
