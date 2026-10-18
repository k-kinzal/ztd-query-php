<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Call\DecisionKind as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DecisionKindTest extends TestCase
{
    public function testUnsupportedDecisionsRetainCustomReasons(): void
    {
        $unsupported = \Deriver\Deriver\Evaluator\Call\CallDecision::unsupported('vendor.custom-capability:version=2', false);
        self::assertSame(Subject::Unsupported, $unsupported->kind);
        self::assertSame('vendor.custom-capability:version=2', $unsupported->reason);
        self::assertFalse($unsupported->fallbackToSource);
        self::assertSame(Subject::Declined, \Deriver\Deriver\Evaluator\Call\CallDecision::declined()->kind);
    }
}
