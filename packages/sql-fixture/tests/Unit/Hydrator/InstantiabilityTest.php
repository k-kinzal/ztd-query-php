<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use Closure;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\Instantiability;
use Tests\Fixture\Hydrator\TestEntity;
use Tests\Fixture\Hydrator\TestEntityNeverBuilt;
use Tests\Fixture\Hydrator\TestTier;

#[CoversClass(Instantiability::class)]
final class InstantiabilityTest extends TestCase
{
    #[Test]
    public function testCallingConstructorIsNothingForAnOrdinaryClass(): void
    {
        self::assertNull((new Instantiability())->callingConstructor(TestEntity::class));
    }

    #[Test]
    public function testCallingConstructorRefusesAnAbstractClass(): void
    {
        self::assertSame(
            'it is abstract',
            (new Instantiability())->callingConstructor(TestEntityNeverBuilt::class)
        );
    }

    #[Test]
    public function testCallingConstructorRefusesAnEnum(): void
    {
        self::assertSame(
            'it is an enum',
            (new Instantiability())->callingConstructor(TestTier::class)
        );
    }

    #[Test]
    public function testCallingConstructorRefusesAConstructorNobodyElseMayCall(): void
    {
        self::assertSame(
            'its constructor is not public',
            (new Instantiability())->callingConstructor(Closure::class)
        );
    }

    #[Test]
    public function testBypassingConstructorIsNothingForAnOrdinaryClass(): void
    {
        self::assertNull((new Instantiability())->bypassingConstructor(TestEntity::class));
    }

    #[Test]
    public function testBypassingConstructorRefusesAnAbstractClass(): void
    {
        self::assertSame(
            'it is abstract',
            (new Instantiability())->bypassingConstructor(TestEntityNeverBuilt::class)
        );
    }

    #[Test]
    public function testBypassingConstructorRefusesAnEnum(): void
    {
        self::assertSame(
            'it is an enum',
            (new Instantiability())->bypassingConstructor(TestTier::class)
        );
    }

    #[Test]
    public function testBypassingConstructorRefusesAnInternalFinalClassPhpWillNotBuildThatWay(): void
    {
        self::assertSame(
            'it is an internal final class, which PHP will not build without its constructor',
            (new Instantiability())->bypassingConstructor(Generator::class)
        );
    }
}
