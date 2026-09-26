<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DispatchDecision
 */
#[CoversClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchTarget::class)]
#[Small]
final class DispatchDecisionTest extends TestCase
{
    public function testKeepsPartialCandidatesOpenByDefault(): void
    {
        $target = new \Deriver\Model\Provider\DispatchTarget('Service::run');
        $decision = new \Deriver\Model\Provider\DispatchDecision([$target]);
        self::assertSame([$target], $decision->targets);
        self::assertFalse($decision->exhaustive);
    }
}
