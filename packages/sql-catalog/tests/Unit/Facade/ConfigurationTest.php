<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\BuiltinCallModel;
use SqlCatalog\Core\Analysis\FunctionModel\NamedModel;
use SqlCatalog\Core\Analysis\FunctionModel\Registry;
use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Facade\Configuration;
use SqlCatalog\Facade\ConfigurationSchema;
use SqlCatalog\Facade\InvalidConfigurationException;
use Tests\Fake\PairModel;

#[CoversClass(Configuration::class)]
#[UsesClass(ConfigurationSchema::class)]
#[UsesClass(Registry::class)]
#[UsesClass(NamedModel::class)]
#[UsesClass(BuiltinCallModel::class)]
#[UsesClass(InvalidConfigurationException::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(LiteralTerm::class)]
final class ConfigurationTest extends TestCase
{
    public function testLoadReadsSettingsRelativeToTheFile(): void
    {
        $path = sys_get_temp_dir() . '/catalog-' . bin2hex(random_bytes(6)) . '.yaml';
        $directory = realpath(dirname($path));
        self::assertNotFalse($directory);
        file_put_contents($path, "paths: [src]\noutput: report\nextensions: [pdo]\nreporter: html\nfunction-models:\n  array_fill: Tests\\Fake\\PairModel\n");
        try {
            $settings = Configuration::load($path);
            self::assertSame(['paths' => [$directory . '/src'], 'output' => [$directory . '/report'], 'extension' => ['pdo'], 'reporter' => ['html'], 'root' => [$directory]], $settings->options);
            self::assertSame(['array_fill' => PairModel::class], $settings->functionModels);
            self::assertSame(realpath($path), $settings->file);
        } finally {
            unlink($path);
        }
    }

    public function testLoadAcceptsAnEmptyDocument(): void
    {
        $path = sys_get_temp_dir() . '/catalog-' . bin2hex(random_bytes(6)) . '.yaml';
        $directory = realpath(dirname($path));
        self::assertNotFalse($directory);
        file_put_contents($path, '');
        try {
            self::assertSame(['root' => [$directory]], Configuration::load($path)->options);
        } finally {
            unlink($path);
        }
    }

    public function testLoadRejectsMissingFiles(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot read configuration');
        Configuration::load('/definitely/missing/.catalog.yaml');
    }

    #[DataProvider('providerInvalidDocuments')]
    public function testLoadRejectsInvalidDocuments(string $yaml): void
    {
        $path = sys_get_temp_dir() . '/catalog-' . bin2hex(random_bytes(6)) . '.yaml';
        $directory = realpath(dirname($path));
        self::assertNotFalse($directory);
        file_put_contents($path, $yaml);
        try {
            $this->expectException(InvalidConfigurationException::class);
            Configuration::load($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * @return list<array{string}>
     */
    public static function providerInvalidDocuments(): array
    {
        return [['[]'], ['[broken'], ['<?php throw new RuntimeException("executed");'], ['!php/object \'O:8:"stdClass":0:{}\''], ["paths: [src]\npaths: [other]"]];
    }

    public function testApplyPreservesTheOriginalRegistryAndOverridesTheBuiltin(): void
    {
        $original = Registry::withBuiltins();
        $configured = (new Configuration(functionModels: ['array_fill' => PairModel::class]))->apply($original);
        $arguments = [Domain::literal(0), Domain::literal(10), Domain::literal('?')];
        self::assertCount(2, $configured->evaluate('array_fill', $arguments)?->soleArray()->entries ?? []);
        self::assertCount(1, $original->evaluate('array_fill', $arguments)?->soleArray()->entries ?? []);
    }
}
