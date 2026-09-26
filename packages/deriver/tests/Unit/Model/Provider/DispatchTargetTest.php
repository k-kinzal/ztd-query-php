<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DispatchTarget
 */
#[CoversClass(\Deriver\Model\Provider\DispatchTarget::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class DispatchTargetTest extends TestCase
{
    public function testRetainsTheGuardAndReceiverBinding(): void
    {
        $guard = \Deriver\Value\Term::parameter('condition', 'bool');
        $receiver = \Deriver\Value\Term::parameter('service', 'Service');
        $target = new \Deriver\Model\Provider\DispatchTarget('Service::run', $receiver, $guard);
        self::assertSame($guard, $target->condition);
        self::assertSame($receiver, $target->receiver);
    }
}
