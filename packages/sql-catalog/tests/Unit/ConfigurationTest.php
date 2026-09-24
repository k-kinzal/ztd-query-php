<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\BuiltinCallModel;
use SqlCatalog\Analysis\FunctionModel\Registry;
use SqlCatalog\Configuration;
use SqlCatalog\InvalidConfigurationException;

#[CoversClass(Configuration::class)]
#[UsesClass(Registry::class)]
#[UsesClass(BuiltinCallModel::class)]
#[UsesClass(InvalidConfigurationException::class)]
final class ConfigurationTest extends TestCase
{
    public function testLoadPreservesTheOriginalRegistry(): void
    {
        $original = Registry::withBuiltins();
        $configured = (new Configuration())->load(__DIR__ . '/../../examples/placeholder-lists.php', $original);
        self::assertTrue($configured->supports('array_fill'));
        self::assertTrue($configured->supports('implode'));
        self::assertFalse($original->supports('array_fill'));
    }

    public function testLoadRejectsMissingFiles(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot read configuration');
        (new Configuration())->load('/definitely/missing/sql-catalog.php', new Registry());
    }

    public function testReadRejectsInvalidConfigurationValues(): void
    {
        $path = sys_get_temp_dir() . '/sql-catalog-config-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($path, '<?php return [];');
        try {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage('must return a callable');
            (new Configuration())->read($path);
        } finally {
            unlink($path);
        }
    }
}
