<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use PhpFuzzer\Config;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class TargetLifetimeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerTargets(): iterable
    {
        foreach (['expression', 'query', 'write', 'statement'] as $mode) {
            yield $mode => [$mode];
        }
    }

    #[DataProvider('providerTargets')]
    public function testTargetKeepsTheServerAliveAfterTheLoaderReturns(string $mode): void
    {
        $config = (static function (string $mode): Config {
            $config = new Config();
            require dirname(__DIR__, 3) . '/fuzz/fuzz_' . $mode . '.php';

            return $config;
        })($mode);
        $this->expectNotToPerformAssertions();
        ($config->target)('');
    }
}
