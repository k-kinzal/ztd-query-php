<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\DispatchRequest;
use Deriver\Project\TargetProfile;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DispatchRequest
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
