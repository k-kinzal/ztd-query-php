<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Provider;

use Deriver\Deriver\Evaluator\Call\Provider\DispatchRequest;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Provider\DispatchRequest
 */
#[CoversClass(DispatchRequest::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Term::class)]
#[Small]
final class DispatchRequestTest extends TestCase
{
    public function testKeepsTheEvaluatedReceiverAndCallSite(): void
    {
        $source = new SourceRef('s', 'f.php', 0, 1);
        $receiver = Term::parameter('service', 'Contract');
        $request = new DispatchRequest($receiver, 'label', false, $source, new TargetProfile());
        self::assertSame($receiver, $request->receiver);
        self::assertSame($source, $request->source);
        self::assertFalse($request->static);
    }
}
