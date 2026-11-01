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
final class ErisForAllCompositionTest extends TestCase
{
    /**
     * @throws JsonException When the stub path or analysis report cannot be encoded
     */
    #[DataProvider('providerCallbacks')]
    public function testCarriesGeneratedTupleTypesIntoTheExecutionCallback(string $arguments, bool $valid): void
    {
        $directory = sys_get_temp_dir() . '/eris-callback-' . bin2hex(random_bytes(8));
        $package = dirname(__DIR__, 3);
        $stub = json_encode($package . '/rules/eris.stub', JSON_THROW_ON_ERROR);
        $filesystem = new Filesystem();
        try {
            $filesystem->dumpFile($directory . '/Example.php', <<<PHP
                <?php
                namespace Example;
                use Eris\Generators;
                final class Pair {
                    public function __construct(public int \$number, public string \$text) {}
                }
                final class ExampleTest extends \PHPUnit\Framework\TestCase {
                    use \Eris\TestTrait;
                    public function exercise(): void {
                        \$this->forAll(Generators::tuple(Generators::int(), Generators::string()))->withMaxSize(10)->then(static function (array \$values): void {
                            \$pair = new Pair({$arguments});
                        });
                    }
                }
                PHP);
            $filesystem->dumpFile($directory . '/phpstan.neon', <<<NEON
                parameters:
                    level: max
                    paths: [Example.php]
                    tmpDir: cache
                    stubFiles: [{$stub}]
                    parallel:
                        maximumNumberOfProcesses: 1
                services:
                    -
                        class: Rules\ErisTupleComposition
                        tags: [phpstan.broker.dynamicStaticMethodReturnTypeExtension]
                    -
                        class: Rules\ErisForAllComposition
                        tags: [phpstan.broker.dynamicMethodReturnTypeExtension]
                NEON);
            $process = new Process([PHP_BINARY, $package . '/vendor/bin/phpstan', 'analyse', '--configuration=' . $directory . '/phpstan.neon', '--autoload-file=' . $package . '/vendor/autoload.php', '--error-format=json', '--no-progress'], $package);
            self::assertSame($valid ? 0 : 1, $process->run(), $process->getErrorOutput() . $process->getOutput());
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
    public static function providerCallbacks(): iterable
    {
        yield 'generated positions' => ['$values[0], $values[1]', true];
        yield 'wrong positions are rejected' => ['$values[1], $values[0]', false];
    }
}
