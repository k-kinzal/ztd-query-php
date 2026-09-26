<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Model\Extensions
 */
#[CoversClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\AbstractDomain::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Model\Intrinsic\IntrinsicDescriptor::class)]
#[UsesClass(\Deriver\Model\Intrinsic\PureIntrinsic::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ExtensionsTest extends TestCase
{
    public function testRegistersVersionedOperationsAndDomains(): void
    {
        $extensions = new \Deriver\Internal\Model\Extensions(new \Deriver\Api\Project\Configuration(intrinsics:[new \Tests\Fake\PolicyOperation('compose')], domains:[new \Tests\Fake\PolicyDomain()]));
        self::assertArrayHasKey('policy.compose', $extensions->intrinsics);
        self::assertArrayHasKey('example.policy', $extensions->domains);
        self::assertSame('1', $extensions->manifest['domain:example.policy']);
    }
    public function testRejectsDuplicateOperations(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        new \Deriver\Internal\Model\Extensions(new \Deriver\Api\Project\Configuration(intrinsics:[new \Tests\Fake\PolicyOperation('compose'),new \Tests\Fake\PolicyOperation('compose')]));
    }
}
