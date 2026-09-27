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
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Model\Intrinsic\IntrinsicDescriptor::class)]
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

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIntrinsicIdentityPairs')]
    public function testRegistersDistinctIntrinsicContractsWithoutDelimiterCollisions(\Deriver\Model\Intrinsic\IntrinsicDescriptor $firstDescriptor, \Deriver\Model\Intrinsic\IntrinsicDescriptor $secondDescriptor): void
    {
        $first = self::createStub(\Deriver\Model\Intrinsic\PureIntrinsic::class);
        $first->method('descriptor')->willReturn($firstDescriptor);
        $second = self::createStub(\Deriver\Model\Intrinsic\PureIntrinsic::class);
        $second->method('descriptor')->willReturn($secondDescriptor);
        $a = new \Deriver\Internal\Model\Extensions(new \Deriver\Api\Project\Configuration(intrinsics:[$first]));
        $b = new \Deriver\Internal\Model\Extensions(new \Deriver\Api\Project\Configuration(intrinsics:[$second]));
        self::assertNotSame($a->manifest, $b->manifest);
    }
    /**
     * @return iterable<string,array{\Deriver\Model\Intrinsic\IntrinsicDescriptor,\Deriver\Model\Intrinsic\IntrinsicDescriptor}>
     */
    public static function providerIntrinsicIdentityPairs(): iterable
    {
        $base = new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'apply', 2, [0]);
        yield 'separator' => [new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1:example', 'apply', 2, [0]),new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'example:apply', 2, [0])];
        yield 'id' => [$base,new \Deriver\Model\Intrinsic\IntrinsicDescriptor('other', '1', 'apply', 2, [0])];
        yield 'version' => [$base,new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '2', 'apply', 2, [0])];
        yield 'operation' => [$base,new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'other', 2, [0])];
        yield 'arity' => [$base,new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'apply', 3, [0])];
        yield 'dependencies' => [$base,new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'apply', 2, [1])];
        yield 'all dependencies' => [$base,new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'apply', 2, [0,1])];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidIntrinsic')]
    public function testRejectsInvalidIntrinsicRegistrationBeforeAdmission(\Deriver\Model\Intrinsic\IntrinsicDescriptor $descriptor, string $message): void
    {
        $intrinsic = self::createStub(\Deriver\Model\Intrinsic\PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn($descriptor);
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage($message);
        new \Deriver\Internal\Model\Extensions(new \Deriver\Api\Project\Configuration(intrinsics:[$intrinsic]));
    }
    /**
     * @return iterable<string,array{\Deriver\Model\Intrinsic\IntrinsicDescriptor,string}>
     */
    public static function providerInvalidIntrinsic(): iterable
    {
        yield 'empty id' => [new \Deriver\Model\Intrinsic\IntrinsicDescriptor('', '1', 'apply', 1, [0]),'MODEL_CONFLICT'];
        yield 'empty version' => [new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '', 'apply', 1, [0]),'MODEL_CONFLICT'];
        yield 'empty operation' => [new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', '', 1, [0]),'MODEL_CONFLICT'];
        yield 'negative arity' => [new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'apply', -1, []),'MODEL_CONFLICT'];
        yield 'negative dependency' => [new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'apply', 1, [-1]),'MODEL_CONTRACT_VIOLATION'];
        yield 'dependency at arity' => [new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'apply', 1, [1]),'MODEL_CONTRACT_VIOLATION'];
    }
    public function testRejectsARepeatedIdEvenWhenOperationsDiffer(): void
    {
        $first = self::createStub(\Deriver\Model\Intrinsic\PureIntrinsic::class);
        $first->method('descriptor')->willReturn(new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'first', 0, []));
        $second = self::createStub(\Deriver\Model\Intrinsic\PureIntrinsic::class);
        $second->method('descriptor')->willReturn(new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example', '1', 'second', 0, []));
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('repeated intrinsic operation or ID');
        new \Deriver\Internal\Model\Extensions(new \Deriver\Api\Project\Configuration(intrinsics:[$first,$second]));
    }
    public function testRegistersZeroArityAndTheLastValidDependency(): void
    {
        $first = self::createStub(\Deriver\Model\Intrinsic\PureIntrinsic::class);
        $first->method('descriptor')->willReturn(new \Deriver\Model\Intrinsic\IntrinsicDescriptor('a', '1', 'first', 0, []));
        $second = self::createStub(\Deriver\Model\Intrinsic\PureIntrinsic::class);
        $second->method('descriptor')->willReturn(new \Deriver\Model\Intrinsic\IntrinsicDescriptor('z', '1', 'second', 2, [1]));
        $extensions = new \Deriver\Internal\Model\Extensions(new \Deriver\Api\Project\Configuration(intrinsics:[$second,$first]));
        self::assertSame(['intrinsic:a','intrinsic:z'], array_keys($extensions->manifest));
        self::assertSame(['second' => $second,'first' => $first], $extensions->intrinsics);
        self::assertSame([], $extensions->domains);
    }
    public function testRejectsARepeatedDomainIdentity(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('invalid or repeated domain identity');
        new \Deriver\Internal\Model\Extensions(new \Deriver\Api\Project\Configuration(domains:[new \Tests\Fake\PolicyDomain(),new \Tests\Fake\PolicyDomain()]));
    }

}
