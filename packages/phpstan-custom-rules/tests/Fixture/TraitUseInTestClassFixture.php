<?php

declare(strict_types=1);

namespace Tests\Fixture;

trait SomeTestTrait
{
    protected function traitHelper(): void
    {
    }
}

namespace Tests\Unit\Fixture;

use Tests\Fixture\SomeTestTrait;
use PHPUnit\Framework\TestCase;

final class TraitUseUnitFixtureTest extends TestCase
{
    use SomeTestTrait;

    public function testSomething(): void
    {
        self::assertTrue(true);
    }
}

namespace Tests\Integration\Fixture;

use Tests\Fixture\SomeTestTrait;
use PHPUnit\Framework\TestCase;

final class TraitUseIntegrationFixtureTest extends TestCase
{
    use SomeTestTrait;

    public function testSomething(): void
    {
        self::assertTrue(true);
    }
}

namespace App\Tests;

use Tests\Fixture\SomeTestTrait;
use PHPUnit\Framework\TestCase;

final class TraitUseOutsideScopeFixtureTest extends TestCase
{
    use SomeTestTrait;

    public function testAllowed(): void
    {
        self::assertTrue(true);
    }
}
