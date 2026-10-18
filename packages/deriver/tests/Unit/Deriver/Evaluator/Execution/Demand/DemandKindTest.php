<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Demand;

use Deriver\Deriver\Evaluator\Execution\Demand\DemandKind as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DemandKindTest extends TestCase
{
    public function testCacheIdentitySeparatesDemandKindsAndPreservesTextualIdentity(): void
    {
        $value = new \Deriver\Deriver\Evaluator\Execution\Demand\Key('snapshot', 'App\\run', 'entry', Subject::Value, 'field', 'context', 'state', 'guard');
        $location = new \Deriver\Deriver\Evaluator\Execution\Demand\Key('snapshot', 'App\\run', 'entry', Subject::Location, 'field', 'context', 'state', 'guard');
        self::assertNotSame($value->id(), $location->id());
        self::assertSame(hash('sha256', serialize(['snapshot', 'App\\run', 'entry', 'value', 'field', 'context', 'state', 'guard', 0])), $value->id());
    }
}
