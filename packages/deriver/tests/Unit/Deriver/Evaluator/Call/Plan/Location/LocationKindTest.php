<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Plan\Location;

use Deriver\Deriver\Evaluator\Call\Plan\Location\LocationKind as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class LocationKindTest extends TestCase
{
    public function testStateLocationsRequireAReceiverAndRetainCustomSlotNames(): void
    {
        $validation = new \Deriver\Deriver\Evaluator\Call\Plan\PlanValidation();
        $footprints = new \Deriver\Deriver\Evaluator\Call\Plan\PlanFootprints($validation);
        $location = new \Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef(Subject::State, 'vendor.custom-slot', \Deriver\Deriver\Evaluator\Call\Plan\Expression::receiver());
        $footprints->location($location, true);
        self::assertSame(['vendor.custom-slot'], $validation->writes);
        self::assertFalse($footprints->shape(new \Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef(Subject::State, 'vendor.custom-slot')));
    }
}
