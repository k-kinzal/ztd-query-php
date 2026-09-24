<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\ConfigurationSchema;
use SqlCatalog\InvalidConfigurationException;
use stdClass;

#[CoversClass(ConfigurationSchema::class)]
#[UsesClass(InvalidConfigurationException::class)]
final class ConfigurationSchemaTest extends TestCase
{
    public function testOptionsPreservesListsAndResolvesOnlyFilesystemPaths(): void
    {
        $options = ConfigurationSchema::options((object) ['paths' => ['src'], 'exclude' => ['*.tmp', 'vendor'], 'root' => '/project', 'kind' => ['select'], 'function-models' => new stdClass()], '/config');
        self::assertSame(['paths' => ['/config/src'], 'exclude' => ['*.tmp', 'vendor'], 'root' => ['/project'], 'kind' => ['select']], $options);
        self::assertSame(['extension' => [], 'root' => ['/config']], ConfigurationSchema::options((object) ['extensions' => []], '/config'));
    }

    #[DataProvider('providerInvalidOptions')]
    public function testOptionsRejectsInvalidSettings(stdClass $data): void
    {
        $this->expectException(InvalidConfigurationException::class);
        ConfigurationSchema::options($data, '/config');
    }

    /**
     * @return list<array{stdClass}>
     */
    public static function providerInvalidOptions(): array
    {
        return [
            [(object) ['unknown' => 'value']],
            [(object) ['reporter' => []]],
            [(object) ['reporter' => '']],
            [(object) ['paths' => 'src']],
            [(object) ['paths' => ['x' => 'src']]],
            [(object) ['paths' => [1]]],
            [(object) ['paths' => ['']]],
        ];
    }

    public function testModelsReadsAMappingAndAllowsNoRegistrations(): void
    {
        self::assertSame([], ConfigurationSchema::models(new stdClass()));
        self::assertSame([], ConfigurationSchema::models((object) ['function-models' => new stdClass()]));
        self::assertSame(['array_fill' => 'App\\Model'], ConfigurationSchema::models((object) ['function-models' => (object) ['array_fill' => 'App\\Model']]));
    }

    #[DataProvider('providerInvalidModels')]
    public function testModelsRejectsMalformedRegistrations(stdClass $data): void
    {
        $this->expectException(InvalidConfigurationException::class);
        ConfigurationSchema::models($data);
    }

    /**
     * @return list<array{stdClass}>
     */
    public static function providerInvalidModels(): array
    {
        return [
            [(object) ['function-models' => []]],
            [(object) ['function-models' => null]],
            [(object) ['function-models' => (object) ['' => 'Model']]],
            [(object) ['function-models' => (object) ['foo' => '']]],
            [(object) ['function-models' => (object) ['foo' => false]]],
        ];
    }

    #[DataProvider('providerPaths')]
    public function testAbsolutePreservesAbsolutePathsAndResolvesRelativePaths(string $path, string $expected): void
    {
        self::assertSame($expected, ConfigurationSchema::absolute($path, '/config'));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerPaths(): array
    {
        return [['src', '/config/src'], ['/src', '/src'], ['C:/src', 'C:/src'], ['C:\\src', 'C:\\src'], ['\\\\server\\src', '\\\\server\\src']];
    }
}
