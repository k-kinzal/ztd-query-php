<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DispatchDecision
 */
#[CoversClass(DispatchDecision::class)]
#[UsesClass(DispatchTarget::class)]
#[Small]
final class DispatchDecisionTest extends TestCase
{
    public function testKeepsPartialCandidatesOpenByDefault(): void
    {
        $target = new DispatchTarget('Service::run');
        $decision = new DispatchDecision([$target]);
        self::assertSame([$target], $decision->targets);
        self::assertFalse($decision->exhaustive);
    }
}
