<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\MockObject\Generator\Generator;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;

final class PhpUnitMockApiFixtureTest extends TestCase
{
    public function testAllowsInterfaceBasedCalls(): void
    {
        $this->createMock(\Stringable::class);
        $this->createConfiguredMock(\Stringable::class, ['__toString' => 'ok']);
        $this->createStub(\Stringable::class);
        self::createConfiguredStub(\Stringable::class, ['__toString' => 'ok']);
        $this->createMockForIntersectionOfInterfaces([\Stringable::class, \Countable::class]);
        self::createStubForIntersectionOfInterfaces([\Stringable::class, \Countable::class]);
    }

    public function testRejectsClassBasedCalls(): void
    {
        $this->createMock(\stdClass::class);
        $this->createConfiguredMock(\stdClass::class, []);
        $this->createStub(\stdClass::class);
        self::createConfiguredStub(\stdClass::class, []);
    }

    public function testRejectsDynamicTargetCalls(): void
    {
        $target = \Stringable::class;
        $this->createMock($target);
        $this->createStub($target);
    }

    public function testRejectsProhibitedApis(): void
    {
        $this->getMockBuilder(\stdClass::class);
        $this->createPartialMock(\stdClass::class, []);
        $this->createTestProxy(\stdClass::class);
        $this->getMockForAbstractClass(\stdClass::class);
        $this->getMockForTrait('SomeTrait');
        $this->getMockFromWsdl('https://example.com/demo.wsdl');
    }

    public function testRejectsDirectInstantiationBypass(): void
    {
        new MockBuilder($this, \stdClass::class);
        new Generator();
    }

    public function testRejectsStaticKeywordCalls(): void
    {
        static::createMock(\stdClass::class);
        static::createStub(\stdClass::class);
    }

    public function testAllowsStaticKeywordWithInterface(): void
    {
        static::createMock(\Stringable::class);
        static::createStub(\Stringable::class);
    }

    public function testRejectsStringTargetInCreateMock(): void
    {
        $this->createMock('stdClass');
        $this->createStub('\stdClass');
    }

    public function testRejectsCaseInsensitiveSelfStaticParent(): void
    {
        self::createMock(\stdClass::class);
    }

    public function testIgnoresNonThisMethodCalls(): void
    {
        $factory = new \App\Tests\Fixture\OutsideTestsNamespaceFixture();
        $factory->createMock(\stdClass::class);
    }
}

namespace App\Tests\Fixture;

final class OutsideTestsNamespaceFixture
{
    public function createMock(string $className): object
    {
        unset($className);

        return new class () {
        };
    }

    public function run(): void
    {
        $this->createMock('not-phpunit');
    }
}
