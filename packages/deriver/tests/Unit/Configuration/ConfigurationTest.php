<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Deriver\Configuration\Configuration;
use Deriver\Configuration\Environment;
use Deriver\Configuration\ReportFormat;
use Deriver\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Configuration::class)]
#[UsesClass(Environment::class)]
final class ConfigurationTest extends TestCase
{
    public function testFromArrayKeepsDefaultsIndependentOfAnalysis(): void
    {
        $configuration = Configuration::fromArray([]);
        self::assertSame(['.'], $configuration->paths);
        self::assertSame(['**/*.php'], $configuration->include);
        self::assertSame([], $configuration->exclude);
        self::assertNull($configuration->phpVersion);
        self::assertSame(ReportFormat::Text, $configuration->format);
    }

    public function testFromArrayAcceptsExplicitLanguageAndEnvironmentSettings(): void
    {
        $configuration = Configuration::fromArray(['paths' => ['app', 'lib'], 'include' => ['*.php'], 'exclude' => ['Generated'], 'phpVersion' => '8.4', 'floatPrecision' => -1, 'environment' => ['APP_ENV' => 'test', '$_GET' => ['page' => 2]], 'standardEvaluators' => false, 'format' => 'json']);
        self::assertSame(['app', 'lib'], $configuration->paths);
        self::assertSame('8.4', $configuration->phpVersion);
        self::assertSame(-1, $configuration->floatPrecision);
        self::assertSame(['APP_ENV' => 'test', '$_GET' => ['page' => 2]], $configuration->environment);
        self::assertFalse($configuration->standardEvaluators);
        self::assertSame(ReportFormat::Json, $configuration->format);
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('providerInvalidSettings')]
    public function testFromArrayRejectsInvalidSettings(array $settings): void
    {
        $this->expectException(ConfigurationException::class);
        Configuration::fromArray($settings);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function providerInvalidSettings(): array
    {
        return [
            'unknown key' => [['includes' => ['*.php']]],
            'scalar roots' => [['paths' => 'src']],
            'mapping roots' => [['paths' => ['root' => 'src']]],
            'no roots' => [['paths' => []]],
            'no includes' => [['include' => []]],
            'empty pattern' => [['exclude' => ['']]],
            'null byte' => [['include' => ["a\0b"]]],
            'wrong pattern type' => [['include' => [42]]],
            'numeric version' => [['phpVersion' => 8.4]],
            'version patch' => [['phpVersion' => '8.4.1']],
            'integer width' => [['integerBits' => 32]],
            'precision type' => [['floatPrecision' => '14']],
            'precision range' => [['floatPrecision' => -2]],
            'boolean type' => [['standardEvaluators' => 'false']],
            'environment type' => [['environment' => 'test']],
            'format type' => [['format' => 1]],
            'unknown format' => [['format' => 'xml']],
        ];
    }
}
