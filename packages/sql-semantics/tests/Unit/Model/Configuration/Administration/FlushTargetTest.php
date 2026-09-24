<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\FlushTarget;
use SqlSemantics\Model\Configuration\Administration\RelayLogFlush;
use SqlSemantics\Model\Configuration\Administration\ServerFlush;

#[CoversClass(FlushTarget::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\Medium]
final class FlushTargetTest extends TestCase
{
    public function testAvailableInAnswersForEveryTargetKind(): void
    {
        self::assertTrue(ServerFlush::Logs->availableIn(90100));
        self::assertFalse((new RelayLogFlush('c'))->availableIn(50651));
    }
}
