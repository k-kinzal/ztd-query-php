<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\InitializerPrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Compares object defaults, scalar coercion modes and allocation identity with PHP 8.3.
 */
#[CoversNothing]
#[Medium]
final class InitializerSemanticsTest extends TestCase
{
    /**
     * @param string $source Trusted PHP fixture
     * @param string $expectedJson Independently recorded result
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[DataProviderExternal(InitializerPrograms::class, 'cases')]
    public function testObjectDefaultInitializersMatchTheRuntime(string $source, string $expectedJson): void
    {
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expected, RuntimeOracle::evaluate($source)->native());
        $result = Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * Runs only trusted fixture declarations in independent runtime compilation units.
     * @param string $library Trusted declaration file
     * @param string $application Trusted caller file
     * @param string $expectedJson Independently expected default behavior
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[DataProviderExternal(InitializerPrograms::class, 'fileCases')]
    public function testObjectDefaultsUseTheDeclaringFileMode(string $library, string $application, string $expectedJson): void
    {
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);
        $runtimeSource = $application.' eval('.var_export(substr($library, 5), true).');';
        self::assertSame($expected, RuntimeOracle::evaluate($runtimeSource)->native());
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('library.php', $library),new SourceFile('app.php', $application)]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
    }
}
