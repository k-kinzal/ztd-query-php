<?php

declare(strict_types=1);

namespace Tests\Integration\Rules;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversNothing]
#[Large]
final class ErisTupleCompositionTest extends TestCase
{
    /**
     * @throws JsonException When test paths cannot be encoded
     */
    #[DataProvider('providerTuples')]
    public function testInfersConstructorOperandsWithoutTypeOverrides(string $arguments, bool $valid): void
    {
        $directory = sys_get_temp_dir() . '/eris-tuple-' . bin2hex(random_bytes(8));
        $package = dirname(__DIR__, 3);
        $filesystem = new Filesystem();
        try {
            $filesystem->dumpFile($directory . '/Example.php', <<<PHP
                <?php
                namespace Example;
                use Eris\Generators;
                final class Pair {
                    public function __construct(public int \$number, public string \$text) {}
                }
                Generators::map(static fn (array \$values): Pair => new Pair(\$values[0], \$values[1]), Generators::tuple({$arguments}));
                PHP);
            $filesystem->dumpFile($directory . '/phpstan.neon', <<<'NEON'
                parameters:
                    level: max
                    paths: [Example.php]
                    tmpDir: cache
                    parallel:
                        maximumNumberOfProcesses: 1
                services:
                    -
                        class: Rules\ErisTupleComposition
                        tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]
                NEON);
            $process = new Process([PHP_BINARY, $package . '/vendor/bin/phpstan', 'analyse', '--configuration=' . $directory . '/phpstan.neon', '--autoload-file=' . $package . '/vendor/autoload.php', '--error-format=json', '--no-progress'], $package);
            self::assertSame($valid ? 0 : 1, $process->run(), $process->getErrorOutput());
            $report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($report);
            self::assertArrayHasKey('totals', $report);
            self::assertIsArray($report['totals']);
            self::assertSame($valid ? 0 : 2, $report['totals']['file_errors']);
        } finally {
            $filesystem->remove($directory);
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerTuples(): iterable
    {
        yield 'positional operands' => ['Generators::int(), Generators::string()', true];
        yield 'array operands' => ['[Generators::int(), Generators::string()]', true];
        yield 'wrong positions are rejected' => ['Generators::string(), Generators::int()', false];
        yield 'raw tuple values' => ["3, 'text'", true];
    }
}
