<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Provider;

use Deriver\Deriver\Evaluator\Call\Provider\DispatchTarget;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Provider\DispatchTarget
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
