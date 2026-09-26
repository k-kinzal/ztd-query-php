<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DispatchRequest
 */
#[CoversClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class DispatchRequestTest extends TestCase
{
    public function testKeepsTheEvaluatedReceiverAndCallSite(): void
    {
        $source = new \Deriver\Api\Reference\SourceRef('s', 'f.php', 0, 1);
        $receiver = \Deriver\Value\Term::parameter('service', 'Contract');
        $request = new \Deriver\Model\Provider\DispatchRequest($receiver, 'label', false, $source, new \Deriver\Api\Project\TargetProfile());
        self::assertSame($receiver, $request->receiver);
        self::assertSame($source, $request->source);
        self::assertFalse($request->static);
    }
}
