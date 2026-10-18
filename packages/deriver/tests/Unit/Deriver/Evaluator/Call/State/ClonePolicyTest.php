<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\State;

use Deriver\Deriver\Evaluator\Call\State\ClonePolicy as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ClonePolicyTest extends TestCase
{
    public function testClonePoliciesProduceDistinctStableManifests(): void
    {
        $copy = new \Deriver\Deriver\Evaluator\Call\StateRegistry([new \Deriver\Deriver\Evaluator\Call\State\StateSlot('vendor.custom-slot', clone: Subject::Copy)]);
        $reset = new \Deriver\Deriver\Evaluator\Call\StateRegistry([new \Deriver\Deriver\Evaluator\Call\State\StateSlot('vendor.custom-slot', clone: Subject::Reset)]);
        self::assertNotSame($copy->manifest, $reset->manifest);
        self::assertSame('mixed:copy:havoc:symbolic', $copy->manifest['slot:vendor.custom-slot']);
        self::assertSame('mixed:reset:havoc:symbolic', $reset->manifest['slot:vendor.custom-slot']);
    }
}
