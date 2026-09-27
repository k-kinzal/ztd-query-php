<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\DispatchProvider;
use Deriver\Model\Provider\DispatchRequest;
use Deriver\Project\TargetProfile;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DispatchProvider
 */
#[CoversClass(DispatchProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DeclarationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchTarget::class)]
#[UsesClass(\Deriver\Model\Provider\EntryPointProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EnvironmentProvider::class)]
#[UsesClass(\Deriver\Model\Provider\ObservationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\Provider::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Term::class)]
#[Small]
final class DispatchProviderTest extends TestCase
{
    public function testResolveSeparatesExhaustiveBindingsFromUnmatchedCalls(): void
    {
        $source = new SourceRef('s', 'f.php', 0, 1);
        $receiver = Term::parameter('service', 'Contract');
        $request = new DispatchRequest($receiver, 'label', false, $source, new TargetProfile());
        $decision = (new \Tests\Fake\MiniContainer())->resolve($request);
        self::assertTrue($decision->exhaustive);
        self::assertSame('GeneratedService::label', $decision->targets[0]->symbol);
    }
}
