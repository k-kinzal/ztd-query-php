<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\BinaryLogReset;
use SqlSemantics\Model\Configuration\Administration\QueryCacheReset;
use SqlSemantics\Model\Configuration\Administration\ResetTarget;

#[CoversClass(ResetTarget::class)]
#[Small]
final class ResetTargetTest extends TestCase
{
    public function testAvailableInAnswersForEveryTargetKind(): void
    {
        self::assertTrue((new BinaryLogReset())->availableIn(50651));
        self::assertFalse((new QueryCacheReset())->availableIn(90100));
    }
}
