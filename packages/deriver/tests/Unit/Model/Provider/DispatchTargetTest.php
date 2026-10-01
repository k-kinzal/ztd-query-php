<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\DispatchTarget;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DispatchTarget
 */
#[CoversClass(DispatchTarget::class)]
#[UsesClass(Term::class)]
#[Small]
final class DispatchTargetTest extends TestCase
{
    public function testRetainsTheGuardAndReceiverBinding(): void
    {
        $guard = Term::parameter('condition', 'bool');
        $receiver = Term::parameter('service', 'Service');
        $target = new DispatchTarget('Service::run', $receiver, $guard);
        self::assertSame($guard, $target->condition);
        self::assertSame($receiver, $target->receiver);
    }
}
