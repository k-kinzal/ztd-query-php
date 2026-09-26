<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DispatchProvider
 */
#[CoversClass(\Deriver\Model\Provider\DispatchProvider::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Model\Provider\DeclarationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchTarget::class)]
#[UsesClass(\Deriver\Model\Provider\EntryPointProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EnvironmentProvider::class)]
#[UsesClass(\Deriver\Model\Provider\ObservationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\Provider::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class DispatchProviderTest extends TestCase
{
    public function testResolveSeparatesExhaustiveBindingsFromUnmatchedCalls(): void
    {
        $source = new \Deriver\Api\Reference\SourceRef('s', 'f.php', 0, 1);
        $receiver = \Deriver\Value\Term::parameter('service', 'Contract');
        $request = new \Deriver\Model\Provider\DispatchRequest($receiver, 'label', false, $source, new \Deriver\Api\Project\TargetProfile());
        $decision = (new \Tests\Fake\MiniContainer())->resolve($request);
        self::assertTrue($decision->exhaustive);
        self::assertSame('GeneratedService::label', $decision->targets[0]->symbol);
    }
}
