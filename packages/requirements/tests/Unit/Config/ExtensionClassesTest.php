<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\ExtensionClasses;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Tests\Fake\CountingRunner;
use Tests\Fake\MemorySource;

#[CoversClass(ExtensionClasses::class)]
#[UsesClass(Fields::class)]
#[Small]
final class ExtensionClassesTest extends TestCase
{
    public function testReadReturnsClassNamesByName(): void
    {
        self::assertSame(['memory' => MemorySource::class, 'counting' => CountingRunner::class], ExtensionClasses::read(['memory' => MemorySource::class, 'counting' => CountingRunner::class]));
    }

    public function testReadAcceptsEmptyMapping(): void
    {
        self::assertSame([], ExtensionClasses::read([]));
    }

    public function testReadKeepsNamesThatAreNotClasses(): void
    {
        self::assertSame(['memory' => 'Missing\\Extension'], ExtensionClasses::read(['memory' => 'Missing\\Extension']));
    }

    #[DataProvider('providerInvalid')]
    public function testReadRejectsInvalidMapping(mixed $value, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        ExtensionClasses::read($value);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerInvalid(): array
    {
        return [
            'not a mapping' => [MemorySource::class, 'extensions must be a mapping.'],
            'list' => [[MemorySource::class], 'extensions must have string keys.'],
            'empty class name' => [['memory' => ''], 'memory must be a nonempty string.'],
            'blank class name' => [['memory' => MemorySource::class, 'counting' => ' '], 'counting must be a nonempty string.'],
            'class name not a string' => [['memory' => 1], 'memory must be a nonempty string.'],
        ];
    }
}
