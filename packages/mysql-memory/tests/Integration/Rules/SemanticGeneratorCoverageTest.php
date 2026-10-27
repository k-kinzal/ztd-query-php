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
final class SemanticGeneratorCoverageTest extends TestCase
{
    /**
     * @param list<string> $expected The diagnostic identifiers in order
     * @throws JsonException When fixture configuration cannot be encoded
     */
    #[DataProvider('providerDeclarations')]
    public function testCoverageDoesNotDependOnARuntimeSentinel(string $model, string $generator, array $expected): void
    {
        $directory = sys_get_temp_dir() . '/semantic-coverage-' . bin2hex(random_bytes(8));
        $package = dirname(__DIR__, 3);
        $filesystem = new Filesystem();
        $sources = json_encode([$directory . '/source/model' => ['SqlSemantics\\', 'Tests\\Generator\\Semantics\\']], JSON_THROW_ON_ERROR);
        try {
            $filesystem->dumpFile($directory . '/source/model/Value.php', "<?php\nnamespace SqlSemantics\\Statement;\n/** @visibility public */\n" . $model);
            $filesystem->dumpFile($directory . '/source/generator/ValueGenerator.php', "<?php\nnamespace Tests\\Generator\\Semantics\\Statement;\n" . $generator);
            $filesystem->dumpFile($directory . '/phpstan.neon', <<<NEON
                parameters:
                    customRulesetUsed: true
                    paths: [source]
                    tmpDir: cache
                    parallel:
                        maximumNumberOfProcesses: 1
                services:
                    -
                        class: Rules\AnalysisFileCollector
                        tags: [phpstan.collector]
                    -
                        class: Rules\SemanticCatalogue
                        arguments:
                            sources: {$sources}
                    -
                        class: Rules\GeneratorDeclarationCollector
                        tags: [phpstan.collector]
                    -
                        class: Rules\SemanticGeneratorCoverageRule
                        tags: [phpstan.rules.rule]
                NEON);
            $process = new Process([
                PHP_BINARY, $package . '/vendor/bin/phpstan', 'analyse',
                '--configuration=' . $directory . '/phpstan.neon',
                '--autoload-file=' . $package . '/vendor/autoload.php',
                '--error-format=raw', '--no-progress', '--memory-limit=512M',
            ], $package);

            $status = $process->run();
            preg_match_all('/\[identifier=([^\]]+)\]/', $process->getOutput(), $matches);

            self::assertSame($expected === [] ? 0 : 1, $status, $process->getErrorOutput());
            self::assertSame($expected, $matches[1], $process->getOutput() . $process->getErrorOutput());
        } finally {
            $filesystem->remove($directory);
        }
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerDeclarations(): iterable
    {
        $record = 'final class CoverageValue {}';
        $generator = <<<'PHP'
            /** @implements \Eris\Generator<\SqlSemantics\Statement\CoverageValue> */
            final class CoverageValueGenerator implements \Eris\Generator {
                public function __invoke($size, \Eris\Random\RandomRange $rand) {
                    return \Eris\Generator\GeneratedValueSingle::fromJustValue(new \SqlSemantics\Statement\CoverageValue());
                }
                public function shrink(\Eris\Generator\GeneratedValue $element) { return $element; }
            }
            PHP;
        yield 'record without Snapshot and without any generator' => [$record, '', ['mysqlMemory.missingGenerator']];
        yield 'interface' => ['interface CoverageValue {}', '', ['mysqlMemory.missingGenerator']];
        yield 'enum' => ['enum CoverageValue { case One; }', '', ['mysqlMemory.missingGenerator']];
        yield 'trait is not a semantic value type' => ['trait CoverageTrait {}', '', []];
        yield 'correct Eris type' => [$record, $generator, []];
        yield 'role generator preserves its subtype' => ['interface CoverageValue {}', str_replace('/** @implements \\Eris\\Generator<\\SqlSemantics\\Statement\\CoverageValue> */', "/**\n * @template T of \\SqlSemantics\\Statement\\CoverageValue\n * @implements \\Eris\\Generator<T>\n */", $generator), []];
        yield 'unbounded template does not establish a semantic type' => [$record, str_replace('/** @implements \\Eris\\Generator<\\SqlSemantics\\Statement\\CoverageValue> */', "/**\n * @template T\n * @implements \\Eris\\Generator<T>\n */", $generator), ['mysqlMemory.invalidGenerator']];
        yield 'wrong Eris result type' => [$record, str_replace('Generator<\SqlSemantics\Statement\CoverageValue>', 'Generator<\stdClass>', $generator), ['mysqlMemory.invalidGenerator']];
        yield 'generator name without Eris' => [$record, 'final class CoverageValueGenerator {}', ['mysqlMemory.invalidGenerator']];
        yield 'abstract generator' => [$record, str_replace('final class CoverageValueGenerator', 'abstract class CoverageValueGenerator', $generator), ['mysqlMemory.invalidGenerator']];
        yield 'unmapped generator does not satisfy the missing one' => [$record, str_replace('class CoverageValueGenerator', 'class DifferentGenerator', $generator), ['mysqlMemory.unmappedGenerator', 'mysqlMemory.missingGenerator']];
    }
}
