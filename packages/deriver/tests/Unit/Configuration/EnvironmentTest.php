<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Deriver\Configuration\Environment;
use Deriver\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    public function testValuesPreserveNativeInputs(): void
    {
        $input = ['$_GET' => ['ids' => [1, 2], 'flag' => false], 'APP_ENV' => 'test', 'EMPTY' => null];
        self::assertSame($input, (new Environment($input))->values);
    }

    /**
     * @param array<array-key, mixed> $input
     */
    #[DataProvider('providerInvalidInputs')]
    public function testValuesRejectInvalidInputs(array $input): void
    {
        $this->expectException(ConfigurationException::class);
        (new Environment($input))->values;
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function providerInvalidInputs(): array
    {
        return ['numeric name' => [[0 => 'test']], 'empty name' => [['' => 'test']], 'object' => [['APP_ENV' => new stdClass()]], 'nested object' => [['$_GET' => ['invalid' => new stdClass()]]]];
    }
}
