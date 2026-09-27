<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Registration;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Model\Registration\Extensions;
use Deriver\Project\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Registration\Extensions
 */
#[CoversClass(Extensions::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Model\Domain\DomainOperations::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ExtensionsTest extends TestCase
{
    public function testRegistersVersionedOperationsAndDomains(): void
    {
        $extensions = new Extensions(new Configuration(intrinsics:[new \Tests\Fake\PolicyOperation('compose')], domains:[new \Tests\Fake\PolicyDomain()]));
        self::assertArrayHasKey('policy.compose', $extensions->intrinsics);
        self::assertArrayHasKey('example.policy', $extensions->domains);
        self::assertSame('1', $extensions->manifest['domain:example.policy']);
    }
    public function testRejectsDuplicateOperations(): void
    {
        $this->expectException(InvalidInputException::class);
        new Extensions(new Configuration(intrinsics:[new \Tests\Fake\PolicyOperation('compose'),new \Tests\Fake\PolicyOperation('compose')]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIntrinsicIdentityPairs')]
    public function testRegistersDistinctIntrinsicContractsWithoutDelimiterCollisions(IntrinsicDescriptor $firstDescriptor, IntrinsicDescriptor $secondDescriptor): void
    {
        $first = self::createStub(PureIntrinsic::class);
        $first->method('descriptor')->willReturn($firstDescriptor);
        $second = self::createStub(PureIntrinsic::class);
        $second->method('descriptor')->willReturn($secondDescriptor);
        $a = new Extensions(new Configuration(intrinsics:[$first]));
        $b = new Extensions(new Configuration(intrinsics:[$second]));
        self::assertNotSame($a->manifest, $b->manifest);
    }
    /**
     * @return iterable<string,array{IntrinsicDescriptor,IntrinsicDescriptor}>
     */
    public static function providerIntrinsicIdentityPairs(): iterable
    {
        $base = new IntrinsicDescriptor('example', '1', 'apply', 2, [0]);
        yield 'separator' => [new IntrinsicDescriptor('example', '1:example', 'apply', 2, [0]),new IntrinsicDescriptor('example', '1', 'example:apply', 2, [0])];
        yield 'id' => [$base,new IntrinsicDescriptor('other', '1', 'apply', 2, [0])];
        yield 'version' => [$base,new IntrinsicDescriptor('example', '2', 'apply', 2, [0])];
        yield 'operation' => [$base,new IntrinsicDescriptor('example', '1', 'other', 2, [0])];
        yield 'arity' => [$base,new IntrinsicDescriptor('example', '1', 'apply', 3, [0])];
        yield 'dependencies' => [$base,new IntrinsicDescriptor('example', '1', 'apply', 2, [1])];
        yield 'all dependencies' => [$base,new IntrinsicDescriptor('example', '1', 'apply', 2, [0,1])];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidIntrinsic')]
    public function testRejectsInvalidIntrinsicRegistrationBeforeAdmission(IntrinsicDescriptor $descriptor, string $message): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn($descriptor);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        new Extensions(new Configuration(intrinsics:[$intrinsic]));
    }
    /**
     * @return iterable<string,array{IntrinsicDescriptor,string}>
     */
    public static function providerInvalidIntrinsic(): iterable
    {
        yield 'empty id' => [new IntrinsicDescriptor('', '1', 'apply', 1, [0]),'MODEL_CONFLICT'];
        yield 'empty version' => [new IntrinsicDescriptor('example', '', 'apply', 1, [0]),'MODEL_CONFLICT'];
        yield 'empty operation' => [new IntrinsicDescriptor('example', '1', '', 1, [0]),'MODEL_CONFLICT'];
        yield 'negative arity' => [new IntrinsicDescriptor('example', '1', 'apply', -1, []),'MODEL_CONFLICT'];
        yield 'negative dependency' => [new IntrinsicDescriptor('example', '1', 'apply', 1, [-1]),'MODEL_CONTRACT_VIOLATION'];
        yield 'dependency at arity' => [new IntrinsicDescriptor('example', '1', 'apply', 1, [1]),'MODEL_CONTRACT_VIOLATION'];
    }
    public function testRejectsARepeatedIdEvenWhenOperationsDiffer(): void
    {
        $first = self::createStub(PureIntrinsic::class);
        $first->method('descriptor')->willReturn(new IntrinsicDescriptor('example', '1', 'first', 0, []));
        $second = self::createStub(PureIntrinsic::class);
        $second->method('descriptor')->willReturn(new IntrinsicDescriptor('example', '1', 'second', 0, []));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('repeated intrinsic operation or ID');
        new Extensions(new Configuration(intrinsics:[$first,$second]));
    }
    public function testRegistersZeroArityAndTheLastValidDependency(): void
    {
        $first = self::createStub(PureIntrinsic::class);
        $first->method('descriptor')->willReturn(new IntrinsicDescriptor('a', '1', 'first', 0, []));
        $second = self::createStub(PureIntrinsic::class);
        $second->method('descriptor')->willReturn(new IntrinsicDescriptor('z', '1', 'second', 2, [1]));
        $extensions = new Extensions(new Configuration(intrinsics:[$second,$first]));
        self::assertSame(['intrinsic:a','intrinsic:z'], array_keys($extensions->manifest));
        self::assertSame(['second' => $second,'first' => $first], $extensions->intrinsics);
        self::assertSame([], $extensions->domains);
    }
    public function testRejectsARepeatedDomainIdentity(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('invalid or repeated domain identity');
        new Extensions(new Configuration(domains:[new \Tests\Fake\PolicyDomain(),new \Tests\Fake\PolicyDomain()]));
    }

}
