<?php

declare(strict_types=1);

namespace Tests\Unit\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Input\InvalidInputException;
use Requirements\Test\BehatRunner;
use Requirements\Test\PhpUnitRunner;
use Requirements\Test\Registry;
use stdClass;
use Tests\Fake\CountingRunner;

#[CoversClass(Registry::class)]
#[UsesClass(PhpUnitRunner::class)]
#[UsesClass(BehatRunner::class)]
#[Small]
final class RegistryTest extends TestCase
{
    public function testGetReturnsTheBuiltInPhpUnitRunner(): void
    {
        self::assertInstanceOf(PhpUnitRunner::class, (new Registry())->get('phpunit'));
    }

    public function testGetReturnsTheBuiltInBehatRunner(): void
    {
        self::assertInstanceOf(BehatRunner::class, (new Registry())->get('behat'));
    }

    public function testGetReturnsTheSameInstanceEachTime(): void
    {
        $registry = new Registry();
        self::assertSame($registry->get('phpunit'), $registry->get('phpunit'));
    }

    public function testGetReturnsAConfiguredExtensionBesideTheBuiltIns(): void
    {
        $registry = new Registry(['custom' => CountingRunner::class]);
        self::assertInstanceOf(CountingRunner::class, $registry->get('custom'));
        self::assertInstanceOf(PhpUnitRunner::class, $registry->get('phpunit'));
        self::assertInstanceOf(BehatRunner::class, $registry->get('behat'));
    }

    public function testGetReturnsAConfiguredExtensionReplacingABuiltIn(): void
    {
        self::assertInstanceOf(CountingRunner::class, (new Registry(['phpunit' => CountingRunner::class]))->get('phpunit'));
    }

    #[DataProvider('providerGetRejectsUnknownNames')]
    public function testGetRejectsUnknownNames(string $name): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("Unknown runner extension: $name");
        (new Registry(['custom' => CountingRunner::class]))->get($name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerGetRejectsUnknownNames(): array
    {
        return [
            'unknown' => ['jest'],
            'different case' => ['PHPUnit'],
        ];
    }

    #[DataProvider('providerGetIsNeverReachedForClassesThatAreNotRunners')]
    public function testGetIsNeverReachedForClassesThatAreNotRunners(string $class): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$class must implement RunnerExtension.");
        (new Registry(['custom' => CountingRunner::class, 'other' => $class]))->get('custom');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerGetIsNeverReachedForClassesThatAreNotRunners(): array
    {
        return [
            'other class' => [stdClass::class],
            'missing class' => ['App\MissingRunner'],
        ];
    }
}
